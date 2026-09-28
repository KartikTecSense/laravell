<?php

namespace App\Http\Controllers;

use App\Services\MysqlDumper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $dumper = new MysqlDumper(DB::connection());
        $filename = Str::slug(config('app.name')).'-'.now()->format('Y-m-d_His').'.sql.gz';

        return response()->streamDownload(function () use ($dumper) {
            set_time_limit(0);

            $gzip = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);

            $dumper->dump(function (string $sql) use ($gzip) {
                echo deflate_add($gzip, $sql, ZLIB_NO_FLUSH);
                flush();
            });

            echo deflate_add($gzip, '', ZLIB_FINISH);
        }, $filename, [
            'Content-Type' => 'application/gzip',
            'Cache-Control' => 'no-store',
        ]);
    }
}
