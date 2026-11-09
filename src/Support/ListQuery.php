<?php
declare(strict_types=1);

namespace Enlixo\Support;

/**
 * One query object for every list endpoint (students, payments, recordings, timetable...).
 * It matches the frontend DataView: ?q=&page=&per_page=&sort=&dir=&<filter>=
 *
 * Each repository declares which columns are searchable, filterable and sortable,
 * so user input never reaches SQL as an identifier.
 */
final class ListQuery
{
    public const MAX_PER_PAGE = 100;

    /** @param array<string,string> $filters */
    private function __construct(
        public readonly string $search,
        public readonly array $filters,
        public readonly ?string $sort,
        public readonly string $direction,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    /**
     * @param list<string> $filterKeys allowed filter names
     * @param list<string> $sortKeys allowed sort names
     */
    public static function fromArray(array $query, array $filterKeys = [], array $sortKeys = [], int $defaultPerPage = 20): self
    {
        $filters = [];
        foreach ($filterKeys as $key) {
            $value = $query[$key] ?? '';
            if (is_string($value) && $value !== '') {
                $filters[$key] = trim($value);
            }
        }
        $sort = isset($query['sort']) && in_array($query['sort'], $sortKeys, true) ? $query['sort'] : null;
        $dir = strtolower((string) ($query['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) ($query['per_page'] ?? $defaultPerPage)));

        return new self(trim(mb_substr((string) ($query['q'] ?? ''), 0, 100)), $filters, $sort, $dir, $page, $perPage);
    }

    public function filter(string $key): ?string
    {
        return $this->filters[$key] ?? null;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Build "WHERE ..." for a search across columns + exact-match filters.
     *
     * @param list<string> $searchColumns SQL expressions, e.g. ['s.full_name', 'shc.student_class_id']
     * @param array<string,string> $filterColumns filter key => SQL expression
     * @return array{0:string,1:array<string,mixed>}
     */
    public function where(array $searchColumns, array $filterColumns, array $baseConditions = [], array $baseParams = []): array
    {
        $conditions = $baseConditions;
        $params = $baseParams;

        if ($this->search !== '' && $searchColumns !== []) {
            $parts = [];
            foreach ($searchColumns as $i => $column) {
                $parts[] = "$column LIKE :q$i";
                $params["q$i"] = '%' . addcslashes($this->search, '%_\\') . '%';
            }
            $conditions[] = '(' . implode(' OR ', $parts) . ')';
        }

        foreach ($filterColumns as $key => $column) {
            if (($value = $this->filter($key)) !== null) {
                $conditions[] = "$column = :f_$key";
                $params["f_$key"] = $value;
            }
        }

        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }

    /** @param array<string,string> $sortColumns sort key => SQL expression */
    public function orderBy(array $sortColumns, string $default): string
    {
        $column = $this->sort !== null && isset($sortColumns[$this->sort]) ? $sortColumns[$this->sort] : $default;
        return "ORDER BY $column {$this->direction}";
    }

    public function limit(): string
    {
        return sprintf('LIMIT %d OFFSET %d', $this->perPage, $this->offset());
    }
}
