<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDdcRangeToMasterKelasBesar extends Migration
{
    public function up()
    {
        $fields = [
            'RangeStart' => ['type' => 'INT', 'null' => true, 'after' => 'warna'],
            'RangeEnd'   => ['type' => 'INT', 'null' => true, 'after' => 'RangeStart'],
        ];
        $this->forge->addColumn('master_kelas_besar', $fields);

        // Backfill dari kdKelas numerik:
        // ratusan ('000'..'900') -> start..start+99,
        // puluhan ('320','330')  -> start..start+9, lainnya start..start.
        // Overlap (320/330 di dalam 300) diselesaikan di resolver
        // dengan aturan most-specific-wins (rentang tersempit menang).
        $this->db->query("
            UPDATE master_kelas_besar
            SET RangeStart = CAST(kdKelas AS UNSIGNED),
                RangeEnd = CAST(kdKelas AS UNSIGNED) + CASE
                    WHEN kdKelas REGEXP '^[0-9]00$' THEN 99
                    WHEN kdKelas REGEXP '^[0-9][0-9]0$' THEN 9
                    ELSE 0
                END
            WHERE kdKelas REGEXP '^[0-9]+$'
        ");
    }

    public function down()
    {
        $this->forge->dropColumn('master_kelas_besar', ['RangeStart', 'RangeEnd']);
    }
}
