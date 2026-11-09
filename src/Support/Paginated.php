<?php
declare(strict_types=1);

namespace Enlixo\Support;

use Enlixo\Core\JsonResponse;

final class Paginated
{
    /** @param list<array<string,mixed>> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly ListQuery $query,
    ) {
    }

    public function toResponse(): JsonResponse
    {
        return JsonResponse::ok($this->items, [
            'total' => $this->total,
            'page' => $this->query->page,
            'per_page' => $this->query->perPage,
            'pages' => (int) max(1, ceil($this->total / $this->query->perPage)),
        ]);
    }
}
