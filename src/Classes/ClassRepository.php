<?php
declare(strict_types=1);

namespace Enlixo\Classes;

use Enlixo\Core\Database;

final class ClassRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array{id:int, name:string}> for filter dropdowns */
    public function optionsForTutor(int $tutorId): array
    {
        return array_map(
            static fn (array $r) => ['id' => (int) $r['class_id'], 'name' => $r['class_name']],
            $this->db->all('SELECT class_id, class_name FROM classes WHERE tutor_id = :t ORDER BY class_name', ['t' => $tutorId]),
        );
    }
}
