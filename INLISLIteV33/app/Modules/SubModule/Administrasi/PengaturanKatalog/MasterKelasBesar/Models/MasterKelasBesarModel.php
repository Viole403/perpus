<?php

namespace MasterKelasBesar\Models;

use CodeIgniter\Model;

class MasterKelasBesarModel extends Model
{
    protected $table = 'master_kelas_besar';
    protected $DBGroup='data';
    protected $primaryKey = 'ID';
    protected $useAutoIncrement = true;
    protected $returnType = 'object';
    protected $useSoftDelete = false;
    protected $protectFields = true;
    
    protected $allowedFields = [
        'kdKelas',
        'namakelas', 
        'warna',
        'RangeStart',
        'RangeEnd',
        'CreateBy',
        'CreateDate',
        'CreateTerminal',
        'UpdateBy',
        'UpdateDate', 
        'UpdateTerminal',
        'Branch_id',
        'active'
    ];

    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';
    protected $createdField = 'CreateDate';
    protected $updatedField = 'UpdateDate';

    protected $validationRules = [
        'kdKelas' => 'required|max_length[3]',
        'namakelas' => 'required|max_length[255]',
        'RangeStart' => 'permit_empty|integer',
        'RangeEnd' => 'permit_empty|integer'
    ];

    protected $validationMessages = [
        'kdKelas' => [
            'required' => 'Kode kelas harus diisi',
            'max_length' => 'Kode kelas maksimal 3 karakter'
        ],
        'namakelas' => [
            'required' => 'Nama kelas harus diisi',
            'max_length' => 'Nama kelas maksimal 255 karakter'
        ],
        'RangeStart' => [
            'integer' => 'Rentang awal harus bilangan bulat (INT)'
        ],
        'RangeEnd' => [
            'integer' => 'Rentang akhir harus bilangan bulat (INT)'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];
}