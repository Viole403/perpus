<?php

namespace Mocks;

use CodeIgniter\Model;

class MockModelConfiguration extends Model
{
    private $mockDatabase = [
        [
            'id' => 1,
            'uri_code' => 'thats-all-folks',
        ],
        [
            'id' => 2,
            'uri_code' => 'everything-is-awesome',
        ],
        [
            'id' => 3,
            'uri_code' => 'ill-be-back',
        ],
        [
            'id' => 4,
            'uri_code' => 'bowties-are-cool',
        ],
        [
            'id' => 5,
            'uri_code' => 'bowties-are-cool-2',
        ],
        [
            'id' => 6,
            'uri_code' => 'may-the-force-be-with-you',
        ],
        [
            'id' => 7,
            'uri_code' => 'dont-stop-me-now',
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
