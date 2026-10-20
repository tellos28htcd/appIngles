<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\State;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Separa un domicilio escrito en una sola línea (como en el Excel) en calle,
 * núm. ext., núm. int., colonia, municipio, estado y C.P. Es una ayuda: si no
 * encuentra con seguridad el número exterior o la colonia, marca el domicilio
 * para revisarlo a mano.
 *
 * Ej.: "AV. DE LA LUZ NO. 1500, INT. 15, COL. PUESTA DEL SOL, QRO." →
 * calle AV. DE LA LUZ, ext. 1500, int. 15, colonia PUESTA DEL SOL, Querétaro, Querétaro.
 */
final class AddressParser
{
    /** Abreviaturas frecuentes del estado → nombre en el catálogo del INEGI. */
    private const STATE_ALIASES = ['qro' => 'queretaro', 'qto' => 'queretaro', 'gto' => 'guanajuato', 'cdmx' => 'ciudad de mexico', 'edomex' => 'mexico'];

    /** @var Collection<int, Municipality>|null */
    private ?Collection $municipalities = null;

    /** @var array<int, Collection<int, Municipality>> */
    private array $municipalitiesByState = [];

    /**
     * @param  int|null  $defaultStateId  estado de la escuela (se usa si el domicilio no lo indica)
     */
    public function __construct(private ?int $defaultStateId = null) {}

    /**
     * @return array{street: ?string, exterior_number: ?string, interior_number: ?string, neighborhood: ?string, state_id: ?int, municipality_id: ?int, postal_code: ?string, address_needs_review: bool}
     */
    public function parse(?string $address): array
    {
        $result = ['street' => null, 'exterior_number' => null, 'interior_number' => null, 'neighborhood' => null,
            'state_id' => null, 'municipality_id' => null, 'postal_code' => null, 'address_needs_review' => false];

        $text = trim(preg_replace('/\s+/', ' ', (string) $address));

        if ($text === '') {
            return $result;
        }

        if (preg_match('/\b(?:C\.?\s?P\.?|CODIGO POSTAL)\s*:?\s*(\d{5})\b/i', $text, $m) || preg_match('/\b(\d{5})\b(?!.*\d{5})/', $text, $m)) {
            $result['postal_code'] = $m[1];
            $text = trim(str_replace($m[0], '', $text), ' ,.');
        }

        $parts = array_values(array_filter(array_map(fn (string $part) => trim($part, " .\t"), explode(',', $text)), fn (string $part) => $part !== ''));

        // "COLONIA QRO" sin coma: el estado pegado al final se separa.
        if ($parts !== [] && preg_match('/^(.+?)\s+(QRO|QTO|QUERETARO|QUER\x{C9}TARO)\.?$/iu', end($parts), $m)) {
            $parts[count($parts) - 1] = trim($m[1]);
            $parts[] = $m[2];
        }

        // Municipio y estado: se buscan desde el final.
        while ($parts !== [] && ($place = $this->place(end($parts))) !== null) {
            $result['municipality_id'] ??= $place['municipality_id'];
            $result['state_id'] ??= $place['state_id'];
            array_pop($parts);
        }

        foreach ($parts as $index => $part) {
            if (preg_match('/^(?:INT(?:ERIOR)?\.?|DEPTO\.?|DEPARTAMENTO|DPTO\.?|LOCAL|CASA)\s*(?:NO\.?\s*)?(.+)$/i', $part, $m)) {
                $result['interior_number'] = Str::limit(trim($m[1]), 20, '');
                unset($parts[$index]);
            }
        }
        $parts = array_values($parts);

        // Calle y número: "CALLE NO. 123", "CALLE #123", "CALLE NUMERO 123" o "CALLE 123".
        $streetPart = array_shift($parts) ?? '';
        if (preg_match('/^(.*?)\s*(?:\bN(?:O|UM|ÚM|UMERO|ÚMERO)?\.?\s*(?:NO\.?)?\s*|#\s*)(\d[\w\-\/]*)(?:\s*(?:INT\.?|INTERIOR|DEPTO\.?)\s*([\w\-\/]+))?\s*(.*)$/iu', $streetPart, $m) && trim($m[1]) !== '') {
            $result['street'] = trim($m[1]);
            $result['exterior_number'] = $m[2];
            $result['interior_number'] ??= ($m[3] ?? '') !== '' ? $m[3] : null;
            $rest = trim($m[4] ?? '');
        } elseif (preg_match('/^(.*?\D)\s+(\d[\w\-\/]*)\s+(?:INT\.?|INTERIOR|DEPTO\.?|DPTO\.?)\s*([\w\-\/]+)\s*(.*)$/iu', $streetPart, $m)) {
            $result['street'] = trim($m[1]);
            $result['exterior_number'] = $m[2];
            $result['interior_number'] ??= $m[3];
            $rest = trim($m[4]);
        } elseif (preg_match('/^(.*\D)\s+(\d[\w\-\/]*)(?:\s+(.*))?$/u', $streetPart, $m)) {
            $result['street'] = trim($m[1]);
            $result['exterior_number'] = $m[2];
            $rest = trim($m[3] ?? '');
        } else {
            $result['street'] = $streetPart;
            $rest = '';
        }

        // Colonia: la marcada con COL./FRACC., o la primera parte que sigue a la calle.
        $neighborhood = $rest !== '' ? $rest : null;
        foreach ($parts as $index => $part) {
            if (preg_match('/^(?:COL(?:ONIA)?\.?|FRACC(?:IONAMIENTO)?\.?|BARRIO|RESIDENCIAL)\s+(.+)$/i', $part, $m)) {
                $neighborhood = $m[0];
                unset($parts[$index]);
                break;
            }
        }
        $neighborhood ??= array_shift($parts);
        $result['neighborhood'] = $neighborhood === null ? null : Str::limit(trim(preg_replace('/^(?:COL(?:ONIA)?\.?)\s+/i', '', $neighborhood)), 120, '');

        // Lo que sobre (p. ej. un fraccionamiento dentro de otro) se agrega a la colonia.
        if ($parts !== [] && $result['neighborhood'] !== null) {
            $result['neighborhood'] = Str::limit($result['neighborhood'].', '.implode(', ', $parts), 120, '');
        }

        $result['street'] = $result['street'] === null ? null : Str::limit($result['street'], 150, '');
        $result['exterior_number'] = $result['exterior_number'] === null ? null : Str::limit($result['exterior_number'], 20, '');

        if ($result['state_id'] === null && $this->defaultStateId !== null) {
            $result['state_id'] = $this->defaultStateId;
        }

        $result['address_needs_review'] = $result['exterior_number'] === null || $result['neighborhood'] === null || $result['municipality_id'] === null;

        return $result;
    }

    /**
     * Estado y municipio escritos en columnas separadas (plantilla de importación).
     * Sin estado se usa el de la escuela; el municipio se busca dentro del estado.
     *
     * @return array{state_id: ?int, municipality_id: ?int}
     */
    public function locate(?string $municipality, ?string $state): array
    {
        $stateId = $state === null ? $this->defaultStateId : ($this->place($state)['state_id'] ?? null);

        if ($municipality === null || $stateId === null) {
            return ['state_id' => $stateId, 'municipality_id' => null];
        }

        $normalized = self::normalize($municipality);
        $normalized = self::STATE_ALIASES[$normalized] ?? $normalized;
        $this->municipalitiesByState[$stateId] ??= Municipality::where('state_id', $stateId)->get();

        return ['state_id' => $stateId, 'municipality_id' => $this->municipalitiesByState[$stateId]
            ->first(fn (Municipality $item) => self::normalize($item->name) === $normalized)?->id];
    }

    /**
     * ¿La parte es un municipio o un estado? (QRO, QUERÉTARO, CORREGIDORA, EL MARQUÉS…)
     *
     * @return array{state_id: ?int, municipality_id: ?int}|null
     */
    private function place(string $part): ?array
    {
        $normalized = self::normalize($part);
        $normalized = self::STATE_ALIASES[$normalized] ?? $normalized;

        $municipality = $this->municipalities()->first(fn (Municipality $municipality) => self::normalize($municipality->name) === $normalized);

        if ($municipality !== null) {
            return ['state_id' => $municipality->state_id, 'municipality_id' => $municipality->id];
        }

        $state = State::all()->first(fn (State $state) => self::normalize($state->name) === $normalized);

        if ($state === null) {
            return null;
        }

        // "QRO" = la ciudad de Querétaro: el municipio con el mismo nombre que el estado.
        $capital = Municipality::where('state_id', $state->id)->get()->first(fn (Municipality $municipality) => self::normalize($municipality->name) === self::normalize($state->name));

        return ['state_id' => $state->id, 'municipality_id' => $capital?->id];
    }

    /** @return Collection<int, Municipality> */
    private function municipalities(): Collection
    {
        return $this->municipalities ??= $this->defaultStateId === null
            ? new Collection
            : Municipality::where('state_id', $this->defaultStateId)->get();
    }

    private static function normalize(string $text): string
    {
        return Str::of(Str::ascii($text))->lower()->replaceMatches('/[^a-z0-9 ]+/', ' ')->squish()->toString();
    }
}
