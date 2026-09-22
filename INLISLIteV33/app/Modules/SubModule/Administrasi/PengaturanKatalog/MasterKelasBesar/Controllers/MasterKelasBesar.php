<?php

namespace MasterKelasBesar\Controllers;

use CodeIgniter\API\ResponseTrait;
use App\Libraries\DataTable;

class MasterKelasBesar extends \Base\Controllers\BaseController
{
    use ResponseTrait;
    
    public $auth;
    public $authorize;
    public $masterkelasbesar;

    function __construct()
    {
        $this->masterkelasbesar = new \MasterKelasBesar\Models\MasterKelasBesarModel();
        $this->auth = \Myth\Auth\Config\Services::authentication();
        $this->authorize = \Myth\Auth\Config\Services::authorization();
    }

    public function index()
    {
        $this->data['title'] = 'Master Kelas Besar';
        return view('MasterKelasBesar\Views\list', $this->data);
    }

    public function create()
    {
        if ($this->request->isAJAX()) {
            $validation = \Config\Services::validation();
            
            $validation->setRules([
                'kdKelas' => 'required|max_length[3]',
                'namakelas' => 'required|max_length[255]',
                'warna' => 'max_length[50]',
                'RangeStart' => 'permit_empty|integer',
                'RangeEnd' => 'permit_empty|integer'
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->fail($validation->getErrors());
            }

            $rangeStart = $this->request->getPost('RangeStart');
            $rangeEnd = $this->request->getPost('RangeEnd');
            $rangeStart = ($rangeStart === '' || $rangeStart === null) ? null : (int) $rangeStart;
            $rangeEnd = ($rangeEnd === '' || $rangeEnd === null) ? null : (int) $rangeEnd;

            if ($rangeStart !== null && $rangeEnd !== null && $rangeStart > $rangeEnd) {
                return $this->fail(['RangeEnd' => 'Rentang akhir tidak boleh lebih kecil dari rentang awal']);
            }

            $overlapError = $this->checkRangeOverlap($rangeStart, $rangeEnd);
            if ($overlapError !== null) {
                return $this->fail(['RangeStart' => $overlapError]);
            }

            $data = [
                'kdKelas' => $this->request->getPost('kdKelas'),
                'namakelas' => $this->request->getPost('namakelas'),
                'warna' => $this->request->getPost('warna'),
                'RangeStart' => $rangeStart,
                'RangeEnd' => $rangeEnd,
                'CreateBy' => user()->id,
                'CreateDate' => date('Y-m-d H:i:s'),
                'CreateTerminal' => $this->request->getIPAddress(),
                'active' => 1
            ];

            if ($this->masterkelasbesar->insert($data)) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'Data berhasil ditambahkan'
                ]);
            } else {
                return $this->fail('Gagal menambahkan data');
            }
        }

        return redirect()->to(base_url('master-kelas-besar'));
    }

    public function detail($id)
    {
        if ($this->request->isAJAX()) {
            $data = $this->masterkelasbesar->find($id);
            if ($data) {
                return $this->respond($data);
            } else {
                return $this->failNotFound('Data tidak ditemukan');
            }
        }
        
        return redirect()->to(base_url('master-kelas-besar'));
    }

    public function update($id)
    {
        if ($this->request->isAJAX()) {
            $validation = \Config\Services::validation();
            
            $validation->setRules([
                'kdKelas' => "required|max_length[3]",
                'namakelas' => 'required|max_length[255]',
                'warna' => 'max_length[50]',
                'RangeStart' => 'permit_empty|integer',
                'RangeEnd' => 'permit_empty|integer'
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->fail($validation->getErrors());
            }

            $rangeStart = $this->request->getPost('RangeStart');
            $rangeEnd = $this->request->getPost('RangeEnd');
            $rangeStart = ($rangeStart === '' || $rangeStart === null) ? null : (int) $rangeStart;
            $rangeEnd = ($rangeEnd === '' || $rangeEnd === null) ? null : (int) $rangeEnd;

            if ($rangeStart !== null && $rangeEnd !== null && $rangeStart > $rangeEnd) {
                return $this->fail(['RangeEnd' => 'Rentang akhir tidak boleh lebih kecil dari rentang awal']);
            }

            $overlapError = $this->checkRangeOverlap($rangeStart, $rangeEnd, (int) $id);
            if ($overlapError !== null) {
                return $this->fail(['RangeStart' => $overlapError]);
            }

            $data = [
                'kdKelas' => $this->request->getPost('kdKelas'),
                'namakelas' => $this->request->getPost('namakelas'),
                'warna' => $this->request->getPost('warna'),
                'RangeStart' => $rangeStart,
                'RangeEnd' => $rangeEnd,
                'UpdateBy' => user()->id,
                'UpdateDate' => date('Y-m-d H:i:s'),
                'UpdateTerminal' => $this->request->getIPAddress()
            ];

            if ($this->masterkelasbesar->update($id, $data)) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'Data berhasil diupdate'
                ]);
            } else {
                return $this->fail('Gagal mengupdate data');
            }
        }

        return redirect()->to(base_url('master-kelas-besar'));
    }

    /**
     * Validasi overlap rentang DDC (INT).
     * Ditolak: irisan sebagian (partial overlap) dengan baris lain yang
     * sama-sama punya rentang. Diizinkan: lepas (disjoint), baris tanpa
     * rentang (NULL), atau tersarang penuh / nested (sub-range seperti
     * 320-329 di dalam 300-399 — dimenangkan yang tersempit saat cetak).
     *
     * @param int|null $start
     * @param int|null $end
     * @param int|null $exceptId Abaikan baris ini (mode update)
     * @return string|null Pesan error, atau null jika lolos
     */
    private function checkRangeOverlap($start, $end, $exceptId = null)
    {
        if ($start === null || $end === null) {
            return null;
        }

        $builder = $this->masterkelasbesar->builder()
            ->select('kdKelas, namakelas, RangeStart, RangeEnd')
            ->where('RangeStart IS NOT NULL')
            ->where('RangeEnd IS NOT NULL')
            ->where('RangeStart <=', $end)
            ->where('RangeEnd >=', $start);
        if ($exceptId !== null) {
            $builder->where('ID !=', $exceptId);
        }

        foreach ($builder->get()->getResultArray() as $row) {
            $rStart = (int) $row['RangeStart'];
            $rEnd = (int) $row['RangeEnd'];
            $nested = ($start >= $rStart && $end <= $rEnd)
                || ($rStart >= $start && $rEnd <= $end);
            if (!$nested) {
                return 'Rentang ' . $start . '-' . $end . ' beririsan sebagian dengan '
                    . $row['kdKelas'] . ' (' . $row['namakelas'] . ': '
                    . $rStart . '-' . $rEnd . '). Hanya rentang lepas atau tersarang penuh yang diizinkan.';
            }
        }

        return null;
    }

    public function delete($id)
    {
        if ($this->masterkelasbesar->delete($id)) {
            session()->setFlashdata('message', alert_success('Data berhasil dihapus'));
        } else {
            session()->setFlashdata('message', alert_error('Gagal menghapus data'));
        }

        return redirect()->to(base_url('master-kelas-besar'));
    }

  public function apply_status($id)
{
    $field = $this->request->getGet('field');
    $value = $this->request->getGet('value');

    if ($field === 'active') {
        $this->masterkelasbesar->update($id, [
            $field => $value,
            'UpdateBy' => user()->id,
            'UpdateDate' => date('Y-m-d H:i:s'),
            'UpdateTerminal' => $this->request->getIPAddress()
        ]);

        $status = $value == 1 ? 'diaktifkan' : 'dinonaktifkan';
        
        // Return JSON response untuk AJAX
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => "Data berhasil {$status}.",
                'status' => $status
            ]);
        }
        
        $this->session->setFlashdata('success', "Data berhasil {$status}.");
    }
    
    return redirect()->to(base_url('master-kelas-besar'));
}

    public function datatable($slug = null)
    {
        $db = db_connect();
        $branch_id = user()->branch_id ?? $this->request->getGet('branch_id');
        
        $builder = $db->table('master_kelas_besar as a')
            ->select('a.ID, a.ID as action, a.kdKelas, a.namakelas, a.warna, a.RangeStart, a.RangeEnd, a.active');

        // if ($branch_id) {
        //     $builder->where('a.Branch_id', $branch_id);
        // }

        $dataTable = DataTable::of($builder)
            ->addNumbering('no')
            ->edit('kdKelas', function ($row) {
                return '<b>' . $row->kdKelas . '</b>';
            })
            ->edit('RangeStart', function ($row) {
                if ($row->RangeStart === null || $row->RangeEnd === null) {
                    return '<span class="text-muted">-</span>';
                }
                return '<b>' . (int) $row->RangeStart . ' &ndash; ' . (int) $row->RangeEnd . '</b>';
            })
            ->edit('warna', function ($row) {
                if ($row->warna) {
                    return '<span class="badge" style="background-color: ' . $row->warna . '; color: white;">' . $row->warna . '</span>';
                }
                return '-';
            })
            ->edit('active', function ($row) {
                if ($row->active == 1) {
                    return '<span class="badge badge-success">Aktif</span>';
                } else {
                    return '<span class="badge badge-secondary">Nonaktif</span>';
                }
            })
            ->edit('action', function ($row) {
                $edit = '<a href="javascript:void(0);" data-href="' . base_url('master-kelas-besar/detail/' . $row->ID) . '" data-toggle="tooltip" data-placement="top" title="Ubah" class="btn btn-sm btn-primary show-data"><i class="pe-7s-note"></i></a>';
                
                if ($row->active == 1) {
                    $status = '<a href="' . base_url('master-kelas-besar/apply_status/' . $row->ID . '?field=active&value=0') . '" data-toggle="tooltip" data-placement="top" title="Nonaktifkan" class="btn btn-sm btn-warning"><i class="pe-7s-close"></i></a>';
                } else {
                    $status = '<a href="' . base_url('master-kelas-besar/apply_status/' . $row->ID . '?field=active&value=1') . '" data-toggle="tooltip" data-placement="top" title="Aktifkan" class="btn btn-sm btn-success"><i class="pe-7s-check"></i></a>';
                }
                
                $delete = '<a href="javascript:void(0);" data-href="' . base_url('master-kelas-besar/delete/' . $row->ID) . '" data-toggle="tooltip" data-placement="top" title="Hapus" class="btn btn-sm btn-danger remove-data"><i class="pe-7s-trash"></i></a>';
                
                return $edit . ' ' . $status . ' ' . $delete;
            })
            ->toJson();
            
        return $dataTable;
    }
}