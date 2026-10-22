<?php

namespace App\Enums;

/** Un club se da presencial (en el aula audiovisual) o en línea. */
enum ClubModality: string
{
    case InPerson = 'in_person';
    case Online = 'online';

    public function label(): string
    {
        return __("clubs.modality.{$this->value}");
    }
}
