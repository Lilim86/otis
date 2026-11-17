<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Libro de Excel (.xlsx) mínimo, sin dependencias: una hoja con título, encabezado con filtro,
 * filas y una fila de totales. Los números quedan como números (se pueden sumar en Excel).
 */
class Xlsx
{
    /**
     * @param  array<string,string>  $columnas  clave => encabezado
     * @param  iterable<array>  $filas
     * @param  array<string,float>  $sumas  clave => total (opcional)
     */
    public static function crear(string $titulo, array $columnas, iterable $filas, array $sumas = [], string $subtitulo = ''): string
    {
        $claves = array_keys($columnas);
        $n = count($claves);
        $xml = [];
        $fila = 1;

        // Estilos: 0 normal, 1 título, 2 encabezado, 3 número, 4 total texto, 5 total número, 6 subtítulo
        $xml[] = self::fila($fila, [self::texto('A'.$fila++, $titulo, 1)]);
        if ($subtitulo !== '') {
            $xml[] = self::fila($fila, [self::texto('A'.$fila++, $subtitulo, 6)]);
        }
        $fila++;
        $encabezado = $fila;
        $xml[] = self::fila($fila++, array_map(fn ($i) => self::texto(self::col($i).$encabezado, $columnas[$claves[$i]], 2), range(0, $n - 1)));

        $anchos = array_map(fn ($c) => mb_strlen((string) $c), array_values($columnas));
        foreach ($filas as $datos) {
            $celdas = [];
            foreach ($claves as $i => $clave) {
                $valor = $datos[$clave] ?? null;
                $celdas[] = self::celda(self::col($i).$fila, $valor);
                $anchos[$i] = max($anchos[$i], mb_strlen(is_float($valor) ? number_format($valor, 2) : (string) $valor));
            }
            $xml[] = self::fila($fila++, $celdas);
        }
        $ultimaDatos = $fila - 1;

        if ($sumas) {
            $celdas = [];
            foreach ($claves as $i => $clave) {
                $ref = self::col($i).$fila;
                if ($i === 0) {
                    $celdas[] = self::texto($ref, 'TOTALES', 4);
                } elseif (isset($sumas[$clave])) {
                    $celdas[] = sprintf('<c r="%s" s="5"><f>SUM(%s%d:%s%d)</f><v>%s</v></c>', $ref, self::col($i), $encabezado + 1, self::col($i), $ultimaDatos, (float) $sumas[$clave]);
                } else {
                    $celdas[] = sprintf('<c r="%s" s="4"/>', $ref);
                }
            }
            $xml[] = self::fila($fila, $celdas);
        }

        $cols = implode('', array_map(fn ($i) => sprintf('<col min="%1$d" max="%1$d" width="%2$d" customWidth="1"/>', $i + 1, min(60, max(10, $anchos[$i] + 3))), range(0, $n - 1)));
        $rango = 'A'.$encabezado.':'.self::col($n - 1).max($encabezado, $ultimaDatos);

        $hoja = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$encabezado.'" topLeftCell="A'.($encabezado + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols><sheetData>'.implode('', $xml).'</sheetData>'
            .'<autoFilter ref="'.$rango.'"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';

        return self::empaquetar($hoja, mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $titulo), 0, 31));
    }

    private static function empaquetar(string $hoja, string $nombreHoja): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.self::esc($nombreHoja ?: 'Reporte').'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', self::estilos());
        $zip->addFromString('xl/worksheets/sheet1.xml', $hoja);
        $zip->close();

        $contenido = file_get_contents($ruta);
        @unlink($ruta);

        return $contenido;
    }

    private static function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="5"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF0D1638"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FF0D1638"/><name val="Calibri"/></font><font><i/><sz val="9"/><color rgb="FF666666"/><name val="Calibri"/></font></fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0D1638"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFBF3DC"/></patternFill></fill></fills>'
            .'<borders count="2"><border/><border><top style="medium"><color rgb="FFC99A2E"/></top></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment wrapText="1" vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="164" fontId="3" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs></styleSheet>';
    }

    private static function fila(int $n, array $celdas): string
    {
        return '<row r="'.$n.'">'.implode('', $celdas).'</row>';
    }

    private static function celda(string $ref, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (is_bool($valor)) {
            $valor = $valor ? 'Sí' : 'No';
        }
        if (is_int($valor) || is_float($valor)) {
            return sprintf('<c r="%s"%s><v>%s</v></c>', $ref, is_float($valor) ? ' s="3"' : '', $valor);
        }

        return self::texto($ref, (string) $valor);
    }

    private static function texto(string $ref, string $valor, int $estilo = 0): string
    {
        return sprintf('<c r="%s" t="inlineStr"%s><is><t xml:space="preserve">%s</t></is></c>', $ref, $estilo ? " s=\"{$estilo}\"" : '', self::esc($valor));
    }

    public static function col(int $i): string
    {
        $letras = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $letras = chr(65 + ($i - 1) % 26).$letras;
        }

        return $letras;
    }

    private static function esc(string $v): string
    {
        // Quita caracteres de control que Excel rechaza.
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
