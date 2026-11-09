<?php
declare(strict_types=1);

use Enlixo\Support\ListQuery;

test('ListQuery whitelists filters/sort and builds safe SQL', function () {
    $q = ListQuery::fromArray(['q' => 'nim%', 'class' => '12', 'evil' => 'x', 'sort' => 'name; DROP', 'page' => '3', 'per_page' => '500'], ['class', 'status'], ['name']);
    T::eq(['class' => '12'], $q->filters);
    T::eq(null, $q->sort);
    T::eq(100, $q->perPage, 'capped');
    T::eq(200, $q->offset());
    [$where, $params] = $q->where(['s.full_name'], ['class' => 'shc.classes_class_id']);
    T::eq('WHERE (s.full_name LIKE :q0) AND shc.classes_class_id = :f_class', $where);
    T::eq('%nim\%%', $params['q0'], 'LIKE wildcards escaped');
    T::eq('ORDER BY s.full_name ASC', $q->orderBy(['name' => 's.name'], 's.full_name'));
});
