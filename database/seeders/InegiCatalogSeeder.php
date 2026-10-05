<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Entidades federativas y municipios del Catálogo Único de Claves
 * Geoestadísticas del INEGI (database/data/inegi_*.csv).
 */
class InegiCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->rows('inegi_states.csv') as [$code, $name, $abbreviation]) {
                State::updateOrCreate(['code' => $code], ['name' => $name, 'abbreviation' => $abbreviation]);
            }

            $stateIds = State::pluck('id', 'code');

            $municipalities = collect($this->rows('inegi_municipalities.csv'))
                ->map(fn (array $row) => [
                    'state_id' => $stateIds[$row[0]] ?? throw new RuntimeException("Entidad {$row[0]} no existe."),
                    'code' => $row[1],
                    'name' => $row[2],
                ]);

            foreach ($municipalities->chunk(500) as $chunk) {
                DB::table('municipalities')->upsert($chunk->all(), ['state_id', 'code'], ['name']);
            }
        });
    }

    /** @return list<array<int, string>> */
    private function rows(string $file): array
    {
        $handle = fopen(database_path("data/{$file}"), 'r');
        fgetcsv($handle, escape: '');

        $rows = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
