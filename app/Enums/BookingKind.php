<?php

namespace App\Enums;

/**
 * Qué se reserva: una práctica (con su actividad de la ruta), un club, una mentoría especial, una entrega de
 * material, una presentación 1 a 1 (el aula queda solo para el alumno) o una presentación en un club.
 */
enum BookingKind: string
{
    case Session = 'session';
    case Club = 'club';
    case Mentoring = 'mentoring';
    case Material = 'material';
    case Presentation = 'presentation';
    case ClubPresentation = 'club_presentation';

    /** Máximo de mentorías especiales por semana (lunes a domingo). */
    public const MENTORING_PER_WEEK = 3;

    /** Máximo de alumnos a la misma hora (mentoría especial y entrega de material: 2); null = sin límite propio. */
    public function perSlotLimit(): ?int
    {
        return match ($this) {
            self::Mentoring, self::Material => 2,
            default => null,
        };
    }

    public function label(): string
    {
        return __("agenda.kinds.{$this->value}");
    }

    /** Va en un aula de práctica (con su teacher del panel o en un aula virtual). */
    public function usesPracticeRoom(): bool
    {
        return in_array($this, [self::Session, self::Mentoring, self::Material, self::Presentation], true);
    }

    /** Va en un club programado (ocupa su cupo y cuenta en el máximo de clubs por día). */
    public function usesClub(): bool
    {
        return in_array($this, [self::Club, self::ClubPresentation], true);
    }

    /** Lleva la actividad de la ruta (LLA), su peso newton y su calificación A/B/C o F de la práctica. */
    public function isPractice(): bool
    {
        return $this === self::Session;
    }

    /** El aula queda solo para ese alumno a esa hora. */
    public function isExclusive(): bool
    {
        return $this === self::Presentation;
    }

    /** Se califica en el Kardex (práctica con su letra; club y presentaciones con firma F, una vez). */
    public function isGradable(): bool
    {
        return in_array($this, [self::Session, self::Club, self::Presentation, self::ClubPresentation], true);
    }

    /** Se puede dar en línea (la entrega de material es solo presencial). */
    public function allowsOnline(): bool
    {
        return $this !== self::Material;
    }

    /** El alumno lo agenda él mismo; los demás tipos los pide a Recepción. */
    public function bookableByStudent(): bool
    {
        return in_array($this, [self::Session, self::Club], true);
    }

    /** Cuenta para el máximo de prácticas por día de Mi Instituto. */
    public function countsAsSession(): bool
    {
        return $this->usesPracticeRoom();
    }

    /** @return list<self> */
    public static function practiceRoomKinds(): array
    {
        return array_values(array_filter(self::cases(), fn (self $kind) => $kind->usesPracticeRoom()));
    }

    /** @return list<self> */
    public static function gradableKinds(): array
    {
        return array_values(array_filter(self::cases(), fn (self $kind) => $kind->isGradable()));
    }
}
