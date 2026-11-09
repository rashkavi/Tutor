<?php
declare(strict_types=1);

namespace Enlixo\Core;

/**
 * Who is calling and on behalf of which tutor (tenant).
 * For a tutor: tutorId === userId. For staff: tutorId is their employer. For a student: studentId is set.
 */
final class AuthContext
{
    public function __construct(
        public readonly int $userId,
        public readonly string $kind, // 'admin' | 'tutor' | 'staff' | 'student' | 'parent'
        public readonly ?int $tutorId = null,
        public readonly ?int $studentId = null,
    ) {
    }

    public function isTutorOwner(): bool
    {
        return $this->kind === 'tutor';
    }

    public function tenantId(): int
    {
        return $this->tutorId ?? throw HttpException::forbidden('This action needs a tutor account.');
    }
}
