<?php

namespace Mocks;

use CodeIgniter\Model;

class MockModelConvention extends Model
{
    private $mockDatabase = [
        [
            'id' => 1,
            'slug' => 'thats-all-folks',
        ],
        [
            'id' => 2,
            'slug' => 'everything-is-awesome',
        ],
        [
            'id' => 3,
            'slug' => 'ill-be-back',
        ],
        [
            'id' => 4,
            'slug' => 'bowties-are-cool',
        ],
        [
            'id' => 5,
            'slug' => 'bowties-are-cool-2',
        ],
        [
            'id' => 6,
            'slug' => 'may-the-force-be-with-you',
        ],
        [
            'id' => 7,
            'slug' => 'dont-stop-me-now',
        ],
    ];

    private $where = [];

    public function where($key, $value)
    {
        foreach ($this->mockDatabase as $data) {
            if ($data[$key] == $value) {
                $this->where[] = $data;
            }
        }

        return $this;
    }

    public function withDeleted($val = true)
    {
        return $this;
    }

    public function first()
    {
        $result = $this->where[0] ?? null;
        $this->where = [];

        return $result;
    }
}
