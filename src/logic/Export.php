<?php

namespace tpext\builder\logic;

use think\Collection;
use tpext\think\App;
use tpext\common\ExtLoader;

/**
 * 数据导出工具：CSV、Excel（PhpSpreadsheet/PHPExcel/xlswriter）、二维码
 */
class Export
{
    /**
     * 当前操作的工作表对象
     *
     * @var \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet|\PHPExcel_Worksheet|null
     */
    protected $worksheet = null;

    /**
     * 导出为CSV（ajax时生成文件返回下载地址，否则直接输出下载流）
     *
     * @param string $title
     * @param array|Collection|\IteratorAggregate|\Generator $data
     * @param array $displayers
     * @param \Closure|null $buildTable
     * @return mixed
     */
    public function toCsv($title, $data, $displayers, $buildTable = null)
    {
        $title = str_replace([' ', '.', '!', '@', '#', '＃', '$', '%', '^', '&', '*', '(', ')', '{', '}', '【', '】', '[', ']'], '', trim($title));

        if (ob_get_contents()) {
            ob_end_clean();
        }

        $fname = '';
        $fp = null;
        if (request()->isAjax()) {
            $dir = App::getRuntimePath() . 'export/' . date('Ymd') . '/';

            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    return json(['code' => 0, 'msg' => __blang('builder_make_dir_failed')]);
                }
            }

            $fname = $dir . $title . "-" . date('Ymd-His') . mt_rand(100, 999) . ".csv";
            $fp = fopen($fname, 'w');
        } else {
            ob_start();
            $fp = fopen('php://output', 'a');
        }

        $headerData = [];
        $body = '';
        $encoding = __blang('builder_export_encoding');

        foreach ($displayers as $key => $displayer) {
            $label = $displayer->getLabel();
            $label = preg_replace('/_?id$/i', __blang('builder_column_id_replace'), $label);
            if ($encoding) {
                $headerData[$key] = mb_convert_encoding($label, __blang('builder_export_encoding'), "UTF-8");
            } else {
                $headerData[$key] = $label;
            }
        }

        fputcsv($fp, $headerData, ',', '"', '\\');

        //来源网络
        $num = 0;
        //每隔$limit行，刷新一下输出buffer，不要太大，也不要太小
        $limit = 5000;
        $row = null;
        $text = null;
        foreach ($data as $d) {
            if ($buildTable) {
                $buildTable([$d]);
            }

            $num++;
            //刷新一下输出buffer，防止由于数据过多造成问题
            if ($limit == $num) {
                if (ExtLoader::isWebman()) {
                    $body .= ob_get_contents();
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                $num = 0;
            }
            $row = [];
            foreach ($displayers as $key => $displayer) {
                $text = $displayer->lockValue(false)->value('')->fill($d)->renderValue();
                $text = $this->replace($text);
                if (is_numeric($text) && !strstr($text, '.')) {
                    $text .= "\t";
                }

                if ($encoding) {
                    $row[$key] = mb_convert_encoding($text, $encoding, "UTF-8");
                } else {
                    $row[$key] = $text;
                }
            }
            fputcsv($fp, $row, ',', '"', '\\');
        }
        unset($row, $text);
        fclose($fp);
        if ($fname) {
            $file = str_replace(App::getRuntimePath() . 'export/', '', $fname);
            return json(['code' => 1, 'msg' => __blang('builder_file_has_been_generated'), 'data' => url('export') . '?path=' . $file]);
        } else {
            if (ExtLoader::isWebman()) {
                $header = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment;filename=' . $title . "-" . date('Ymd-His') . mt_rand(100, 999) . ".csv",
                    'Cache-Control' => 'max-age=0',
                ];
                $body .= ob_get_clean();
                return response()->withHeaders($header)->withBody($body);
            } else {
                $body .= ob_get_clean();
                return download($body, $title . "-" . date('Ymd-His') . mt_rand(100, 999) . ".csv", true, 0);
            }
        }
    }

    /**
     * 清理单元格文本：去HTML标签、替换HTML实体和特殊空白字符
     *
     * @param string|null $text
     * @return string
     */
    protected function replace($text)
    {
        if ($text === '' || $text === null) {
            return '';
        }
        $text = strip_tags($text);
        $text = str_replace(['\u00A0', '\u0020', '\u2800', '\u3000', '　'], '', $text);
        $text = str_replace(['&nbsp;', '&gt;', '&lt;', '&eq;', '&egt;', '&elt;'], [' ', '>', '<', '=', '>=', '<='], $text);

        return $text;
    }

    /**
     * 导出为Excel（依赖PhpSpreadsheet或PHPExcel）
     *
     * @param string $title
     * @param array|Collection|\IteratorAggregate|\Generator $data
     * @param array $displayers
     * @param string $type
     * @param \Closure|null $buildTable
     * @return mixed
     */
    public function toExcel($title, $data, $displayers, $type, $buildTable = null)
    {
        if ($type == 'xlsx' && class_exists('\\Vtiful\\Kernel\\Excel')) {
            return $this->toXlswriter($title, $data, $displayers, $type, $buildTable);
        }
        $title = str_replace([' ', '.', '!', '@', '#', '＃', '$', '%', '^', '&', '*', '(', ')', '{', '}', '【', '】', '[', ']'], '', trim($title));

        if (ob_get_contents()) {
            ob_end_clean();
        }
        $lib = '';

        $obj = null;
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $obj = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $this->worksheet = $obj->getActiveSheet();
            $lib = 'PhpOffice';
        } else if (class_exists('\\PHPExcel')) {
            $obj = new \PHPExcel();
            $this->worksheet = $obj->getActiveSheet();
            $lib = 'PHPExcel';
        } else {
            return json(['code' => 0, 'msg' => 'PHPExcel or PhpSpreadsheet required', 'data' => '']);
        }

        $this->worksheet->setTitle($title);

        // 列标
        $list = [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ',
            'BA', 'BB', 'BC', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BK', 'BL', 'BM', 'BN', 'BO', 'BP', 'BQ', 'BR', 'BS', 'BT', 'BU', 'BV', 'BW', 'BX', 'BY', 'BZ',
            'CA', 'CB', 'CC', 'CD', 'CE', 'CF', 'CG', 'CH', 'CI', 'CJ', 'CK', 'CL', 'CM', 'CN', 'CO', 'CP', 'CQ', 'CR', 'CS', 'CT', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ',
            'DA', 'DB', 'DC', 'DD', 'DE', 'DF', 'DG', 'DH', 'DI', 'DJ', 'DK', 'DL', 'DM', 'DN', 'DO', 'DP', 'DQ', 'DR', 'DS', 'DT', 'DU', 'DV', 'DW', 'DX', 'DY', 'DZ',
            'EA', 'EB', 'EC', 'ED', 'EE', 'EF', 'EG', 'EH', 'EI', 'EJ', 'EK', 'EL', 'EM', 'EN', 'EO', 'EP', 'EQ', 'ER', 'ES', 'ET', 'EU', 'EV', 'EW', 'EX', 'EY', 'EZ',
            'FA', 'FB', 'FC', 'FD', 'FE', 'FF', 'FG', 'FH', 'FI', 'FJ', 'FK', 'FL', 'FM', 'FN', 'FO', 'FP', 'FQ', 'FR', 'FS', 'FT', 'FU', 'FV', 'FW', 'FX', 'FY', 'FZ',
            'GA', 'GB', 'GC', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GJ', 'GK', 'GL', 'GM', 'GN', 'GO', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GV', 'GW', 'GX', 'GY', 'GZ', //够用就行
        ];

        // 填充第一行数据

        foreach ($displayers as $k => $displayer) {
            $label = $displayer->getLabel();
            $label = preg_replace('/_?id$/i', __blang('builder_column_id_replace'), $label);
            $this->worksheet->setCellValue($list[$k] . '1', $label);
        }
        $num = 0;
        $text = null;
        $c = 0;
        foreach ($data as $d) {
            if ($buildTable) {
                $buildTable([$d]);
            }

            $c = 0;
            foreach ($displayers as $key => $displayer) {
                $text = $displayer->lockValue(false)->value('')->fill($d)->renderValue();
                $text = $this->replace($text);
                if ($lib == 'PhpOffice') {
                    $this->worksheet->setCellValueExplicit($list[$c] . ($num + 2), $text, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $this->worksheet->setCellValueExplicit($list[$c] . ($num + 2), $text, \PHPExcel_Cell_DataType::TYPE_STRING); //将其设置为文本格式
                }

                $c++;
            }
            $num++;
        }

        unset($text);
        $objWriter = null;
        if ($type == 'xls') {
            if ($lib == 'PhpOffice') {
                $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xls($obj);
                $objWriter->setPreCalculateFormulas(false);
            } else {
                $objWriter = new \PHPExcel_Writer_Excel5($obj);
                $objWriter->setPreCalculateFormulas(false);
            }

            if (request()->isAjax()) {
                $dir = App::getRuntimePath() . 'export/' . date('Ymd') . '/';

                if (!is_dir($dir)) {
                    if (!mkdir($dir, 0755, true)) {
                        return json(['code' => 0, 'msg' => __blang('builder_make_dir_failed')]);
                    }
                }

                $fname = $dir . $title . "-" . date('Ymd-His') . mt_rand(100, 999) . ".xls";
                $objWriter->save($fname);
                $obj->disconnectWorksheets();
                $file = str_replace(App::getRuntimePath() . 'export/', '', $fname);
                return json(['code' => 1, 'msg' => __blang('builder_file_has_been_generated'), 'data' => url('export') . '?path=' . $file]);
            } else {
                if (ExtLoader::isWebman()) {
                    ob_start();
                    $objWriter->save('php://output');
                    $obj->disconnectWorksheets();
                    return response()->withHeaders([
                        'Content-Type' => 'application/vnd.ms-excel',
                        'Content-Disposition' => 'attachment;filename=' . $title . "-" . date('Ymd-His') . mt_rand(100, 999) . ".xls",
                        'Cache-Control' => 'max-age=0',
                    ])->withBody(ob_get_clean());
                } else {
                    ob_start();
                    $objWriter->save('php://output');
                    $obj->disconnectWorksheets();
                    return download(ob_get_clean(), $title . "-" . date('Ymd-His') . mt_rand(100, 999) . ".xls", true, 0);
                }
            }
        } else {
            if ($lib == 'PhpOffice') {
                $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($obj);
                $objWriter->setPreCalculateFormulas(false);
            } else {
                $objWriter = new \PHPExcel_Writer_Excel2007($obj);
                $objWriter->setPreCalculateFormulas(false);
            }

            if (request()->isAjax()) {
                $dir = App::getRuntimePath() . 'export/' . date('Ymd') . '/';
                if (!is_dir($dir)) {
                    if (!mkdir($dir, 0755, true)) {
                        return json(['code' => 0, 'msg' => __blang('builder_make_dir_failed')]);
                    }
                }

                $fname = $dir . $title . "-" . date('Ymd-His') . mt_rand(100, 999) . ".xlsx";
                $objWriter->save($fname);
                $obj->disconnectWorksheets();

                $file = str_replace(App::getRuntimePath() . 'export/', '', $fname);
                return json(['code' => 1, 'msg' => __blang('builder_file_has_been_generated'), 'data' => url('export') . '?path=' . $file]);
            } else {
                if (ExtLoader::isWebman()) {
                    ob_start();
                    $objWriter->save('php://output');
                    $obj->disconnectWorksheets();

                    return response()->withHeaders([
                        'Content-Type' => 'application/vnd.ms-excel',
                        'Content-Disposition' => 'attachment;filename=' . $title . "-" . date('Ymd-His') . mt_rand(100, 999) . ".xlsx",
                        'Cache-Control' => 'max-age=0',
                    ])->withBody(ob_get_clean());
                } else {
                    ob_start();
                    $objWriter->save('php://output');
                    $obj->disconnectWorksheets();
                    return download(ob_get_clean(), $title . "-" . date('Ymd-His') . mt_rand(100, 999) . ".xlsx", true, 0);
                }
            }
        }
    }

    /**
     * 使用xlswriter扩展导出为xlsx
     *
     * @param string $title
     * @param array|Collection|\IteratorAggregate|\Generator $data
     * @param array $displayers
     * @param string $type
     * @param \Closure|null $buildTable
     * @return mixed
     */
    public function toXlswriter($title, $data, $displayers, $type, $buildTable = null)
    {
        $title = str_replace([' ', '.', '!', '@', '#', '＃', '$', '%', '^', '&', '*', '(', ')', '{', '}', '【', '】', '[', ']'], '', trim($title));

        if (ob_get_contents()) {
            ob_end_clean();
        }

        $header = [];
        $body = [];

        foreach ($displayers as $k => $displayer) {
            $label = $displayer->getLabel();
            $label = preg_replace('/_?id$/i', __blang('builder_column_id_replace'), $label);
            $header[] = $label;
        }
        $num = 0;
        $text = null;
        foreach ($data as $d) {
            if ($buildTable) {
                $buildTable([$d]);
            }
            $row = [];
            foreach ($displayers as $key => $displayer) {
                $text = $displayer->lockValue(false)->value('')->fill($d)->renderValue();
                $text = $this->replace($text);
                $row[] = $text;
            }
            $body[] = $row;
            $num++;
        }
        unset($text);

        $dir = App::getRuntimePath() . 'export/' . date('Ymd') . '/';
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return json(['code' => 0, 'msg' => __blang('builder_make_dir_failed')]);
            }
        }

        $config = [
            'path' => $dir // xlsx文件保存路径
        ];
        $excel = new \Vtiful\Kernel\Excel($config);
        $fname = $title . "-" . date('Ymd-His') . mt_rand(100, 999) . ".xlsx";
        // fileName 会自动创建一个工作表，你可以自定义该工作表名称，工作表名称为可选参数
        $filePath = $excel->fileName($fname)
            ->header($header)
            ->data($body)
            ->output();

        if (request()->isAjax()) {
            $file = str_replace(App::getRuntimePath() . 'export/', '', $filePath);
            return json(['code' => 1, 'msg' => '文件已生成', 'data' => url('export') . '?path=' . $file]);
        } else {
            if (ExtLoader::isWebman()) {
                return response()->download($filePath, $fname);
            }
            return download($filePath, $fname);
        }
    }

    /**
     * 批量导出二维码并打包为zip（依赖phpqrcode库）
     *
     * @param string $title
     * @param array|Collection|\IteratorAggregate|\Generator $data
     * @param string $codeField 数据中要编码的字段名
     * @param integer $QR_ECLEVEL QR_ECLEVEL_L=0,QR_ECLEVEL_M=1,QR_ECLEVEL_Q=2,QR_ECLEVEL_H=3;
     * @param integer $size 二维码大小
     * @param bool $requireLib 是否需要再引入`phpqrcode`库，如果在调用此方法前已经[require_once]引入了相关库，则设置为`false`
     * @param \Closure|null $buildTable
     * @return mixed
     */
    public function toQrcode($title = '二维码', $data = [], $codeField = 'code', $QR_ECLEVEL = 3, $size = 5, $requireLib = true, $buildTable = null)
    {
        if ($requireLib) {
            require_once App::getRootPath() . 'extend/phpqrcode/phpqrcode.php';
        }

        $time = date('YmdHis') . '_' . mt_rand(100, 999);

        $dir = App::getRuntimePath() . 'export/' . date('Ymd') . '/';
        $dir2 = App::getRuntimePath() . 'export/' . date('Ymd') . '/qr' . $time . '/';

        if (!is_dir($dir2)) {
            if (!mkdir($dir2, 0755, true)) {
                return json(['code' => 0, 'msg' => __blang('builder_make_dir_failed')]);
            }
        }

        $files = [];
        foreach ($data as $d) {
            if ($buildTable) {
                $buildTable([$d]);
            }

            $fillename = $dir2 . $d[$codeField] . '.png';
            \QRcode::png($d[$codeField], $fillename, $QR_ECLEVEL, $size);
            $files[] = $fillename;
        }

        $zip = new \ZipArchive();

        $zfile = $dir . $title . '_共' . count($files) . '个' . date('Ymd-His') . mt_rand(100, 999) . '.zip';

        $zip->open($zfile, \ZipArchive::CREATE);  //打开压缩包

        foreach ($files as $imgFile) {
            $zip->addFile($imgFile, basename($imgFile));  //向压缩包中添加文件
        }

        $zip->close(); //关闭压缩包

        try {
            $this->deleteDir($dir2);
        } catch (\Exception $e) {
            trace($e->getMessage());
        }

        $file = str_replace(App::getRuntimePath() . 'export/', '', $zfile);

        if (request()->isAjax()) {
            return json(['code' => 1, 'msg' => __blang('builder_file_has_been_generated'), 'data' => url('export') . '?path=' . $file]);
        } else {
            return redirect(url('export') . '?path=' . $file);
        }
    }

    /**
     * 递归删除目录及其全部内容
     *
     * @param string $path
     * @return void
     */
    public function deleteDir($path)
    {
        if (is_dir($path)) {

            $dir = opendir($path);

            while (false !== ($file = readdir($dir))) {

                if (($file != '.') && ($file != '..')) {

                    $sonDir = $path . DIRECTORY_SEPARATOR . $file;
                    if (is_dir($sonDir)) {
                        $this->deleteDir($sonDir);
                    } else {
                        unlink($sonDir);
                    }
                }
            }
            closedir($dir);
            rmdir($path);
        }
    }
}
