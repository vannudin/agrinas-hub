<?php
namespace App\Support;

/**
 * Berkas kosong yang SAH untuk tiap jenis dokumen Office, dibuat tanpa pustaka
 * eksternal (lihat Zip untuk alasannya: kontainer tanpa ekstensi zip).
 *
 * Semuanya OOXML minimal — cukup untuk dibuka editor, lalu EDITOR yang menulis
 * isinya. Yang penting bukan kelengkapannya, melainkan: (a) arsipnya sah,
 * (b) relasi antar-bagiannya konsisten, karena editor menolak berkas yang
 * menunjuk bagian yang tidak ada.
 */
final class TemplateDokumen
{
    public const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

    /** @return array<string,string> nama di arsip => isi */
    public static function bagian(string $jenis): array
    {
        return match ($jenis) {
            'xlsx' => self::xlsx(),
            'pptx' => self::pptx(),
            default => self::docx(),
        };
    }

    // ------------------------------------------------------------------ Word
    public static function docx(): array
    {
        return [
            '[Content_Types].xml' => self::XML
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                .'</Types>',
            '_rels/.rels' => self::XML
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                .'</Relationships>',
            'word/document.xml' => self::XML
                .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
                .'<w:p/>'
                .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
                .'<w:pgMar w:top="1417" w:right="1417" w:bottom="1417" w:left="1417"/></w:sectPr>'
                .'</w:body></w:document>',
        ];
    }

    // ----------------------------------------------------------------- Excel
    public static function xlsx(): array
    {
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        return [
            '[Content_Types].xml' => self::XML
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>',
            '_rels/.rels' => self::XML
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="'.$rns.'/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => self::XML
                .'<workbook xmlns="'.$ns.'" xmlns:r="'.$rns.'">'
                .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => self::XML
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="'.$rns.'/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>',
            'xl/worksheets/sheet1.xml' => self::XML
                .'<worksheet xmlns="'.$ns.'"><sheetData/></worksheet>',
        ];
    }

    // ---------------------------------------------------------- PowerPoint
    // Presentasi butuh rangkaian yang lengkap (slide -> layout -> master ->
    // theme); editor menolak presentasi yang menunjuk bagian tak ada. Semua di
    // bawah ini boilerplate minimal dari skema PresentationML.
    public static function pptx(): array
    {
        $ns = 'http://schemas.openxmlformats.org/presentationml/2006/main';
        $ans = 'http://schemas.openxmlformats.org/drawingml/2006/main';
        $rns = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $rel = 'http://schemas.openxmlformats.org/package/2006/relationships';

        $judul = '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Judul"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
            .'<p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>'
            .'<p:spPr><a:xfrm><a:off x="838200" y="365125"/><a:ext cx="10515600" cy="1325563"/></a:xfrm></p:spPr>'
            .'<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="id-ID"/></a:p></p:txBody></p:sp>';

        $isi = '<p:sp><p:nvSpPr><p:cNvPr id="3" name="Isi"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
            .'<p:nvPr><p:ph idx="1"/></p:nvPr></p:nvSpPr>'
            .'<p:spPr><a:xfrm><a:off x="838200" y="1825625"/><a:ext cx="10515600" cy="4351338"/></a:xfrm></p:spPr>'
            .'<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="id-ID"/></a:p></p:txBody></p:sp>';

        return [
            '[Content_Types].xml' => self::XML
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>'
                .'<Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>'
                .'<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>'
                .'<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>'
                .'<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>'
                .'</Types>',
            '_rels/.rels' => self::XML.'<Relationships xmlns="'.$rel.'">'
                .'<Relationship Id="rId1" Type="'.$rns.'/officeDocument" Target="ppt/presentation.xml"/>'
                .'</Relationships>',
            'ppt/presentation.xml' => self::XML
                .'<p:presentation xmlns:p="'.$ns.'" xmlns:a="'.$ans.'" xmlns:r="'.$rns.'">'
                .'<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId2"/></p:sldMasterIdLst>'
                .'<p:sldIdLst><p:sldId id="256" r:id="rId1"/></p:sldIdLst>'
                .'<p:sldSz cx="12192000" cy="6858000"/><p:notesSz cx="6858000" cy="9144000"/>'
                .'</p:presentation>',
            'ppt/_rels/presentation.xml.rels' => self::XML.'<Relationships xmlns="'.$rel.'">'
                .'<Relationship Id="rId1" Type="'.$rns.'/slide" Target="slides/slide1.xml"/>'
                .'<Relationship Id="rId2" Type="'.$rns.'/slideMaster" Target="slideMasters/slideMaster1.xml"/>'
                .'<Relationship Id="rId3" Type="'.$rns.'/theme" Target="theme/theme1.xml"/>'
                .'</Relationships>',
            'ppt/slides/slide1.xml' => self::XML
                .'<p:sld xmlns:p="'.$ns.'" xmlns:a="'.$ans.'" xmlns:r="'.$rns.'"><p:cSld><p:spTree>'
                .'<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
                .$judul.$isi
                .'</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>',
            'ppt/slides/_rels/slide1.xml.rels' => self::XML.'<Relationships xmlns="'.$rel.'">'
                .'<Relationship Id="rId1" Type="'.$rns.'/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
                .'</Relationships>',
            'ppt/slideLayouts/slideLayout1.xml' => self::XML
                .'<p:sldLayout xmlns:p="'.$ns.'" xmlns:a="'.$ans.'" xmlns:r="'.$rns.'" type="titleAndContent" preserve="1">'
                .'<p:cSld name="Judul dan Isi"><p:spTree>'
                .'<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
                .$judul.$isi
                .'</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sldLayout>',
            'ppt/slideLayouts/_rels/slideLayout1.xml.rels' => self::XML.'<Relationships xmlns="'.$rel.'">'
                .'<Relationship Id="rId1" Type="'.$rns.'/slideMaster" Target="../slideMasters/slideMaster1.xml"/>'
                .'</Relationships>',
            'ppt/slideMasters/slideMaster1.xml' => self::XML
                .'<p:sldMaster xmlns:p="'.$ns.'" xmlns:a="'.$ans.'" xmlns:r="'.$rns.'">'
                .'<p:cSld><p:spTree>'
                .'<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
                .'</p:spTree></p:cSld>'
                .'<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2"'
                .' accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6"'
                .' hlink="hlink" folHlink="folHlink"/>'
                .'<p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>'
                // txStyles WAJIB: tanpa ini editor menolak presentasi
                // (terverifikasi: LibreOffice menjawab "source file could not be loaded").
                .self::txStyles()
                .'</p:sldMaster>',
            'ppt/slideMasters/_rels/slideMaster1.xml.rels' => self::XML.'<Relationships xmlns="'.$rel.'">'
                .'<Relationship Id="rId1" Type="'.$rns.'/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
                .'<Relationship Id="rId2" Type="'.$rns.'/theme" Target="../theme/theme1.xml"/>'
                .'</Relationships>',
            'ppt/theme/theme1.xml' => self::XML
                .'<a:theme xmlns:a="'.$ans.'" name="Office"><a:themeElements>'
                .'<a:clrScheme name="Office">'
                .'<a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1>'
                .'<a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>'
                .'<a:dk2><a:srgbClr val="44546A"/></a:dk2><a:lt2><a:srgbClr val="E7E6E6"/></a:lt2>'
                .'<a:accent1><a:srgbClr val="4472C4"/></a:accent1><a:accent2><a:srgbClr val="ED7D31"/></a:accent2>'
                .'<a:accent3><a:srgbClr val="A5A5A5"/></a:accent3><a:accent4><a:srgbClr val="FFC000"/></a:accent4>'
                .'<a:accent5><a:srgbClr val="5B9BD5"/></a:accent5><a:accent6><a:srgbClr val="70AD47"/></a:accent6>'
                .'<a:hlink><a:srgbClr val="0563C1"/></a:hlink><a:folHlink><a:srgbClr val="954F72"/></a:folHlink>'
                .'</a:clrScheme>'
                .'<a:fontScheme name="Office">'
                .'<a:majorFont><a:latin typeface="Calibri Light"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont>'
                .'<a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont>'
                .'</a:fontScheme>'
                .'<a:fmtScheme name="Office"><a:fillStyleLst>'
                .'<a:solidFill><a:schemeClr val="phClr"/></a:solidFill>'
                .'<a:solidFill><a:schemeClr val="phClr"/></a:solidFill>'
                .'<a:solidFill><a:schemeClr val="phClr"/></a:solidFill>'
                .'</a:fillStyleLst><a:lnStyleLst>'
                .'<a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>'
                .'<a:ln w="12700"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>'
                .'<a:ln w="19050"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>'
                .'</a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle>'
                .'<a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle>'
                .'</a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill>'
                .'<a:solidFill><a:schemeClr val="phClr"/></a:solidFill>'
                .'<a:solidFill><a:schemeClr val="phClr"/></a:solidFill>'
                .'</a:bgFillStyleLst></a:fmtScheme>'
                .'</a:themeElements><a:objectDefaults/><a:extraClrSchemeLst/></a:theme>',
        ];
    }

    /** Gaya teks master: bagian yang paling sering terlupa, dan wajib ada. */
    private static function txStyles(): string
    {
        $nsA = 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"';

        // Satu blok paragraf dipakai ulang untuk body & other; hanya ukuran font
        // dan typeface-nya yang berbeda antara judul dan isi.
        $lv = function (string $sz, string $latin): string {
            return '<a:lvl1pPr algn="l" defTabSz="914400" rtl="0" eaLnBrk="1" latinLnBrk="0" hangingPunct="1">'
                .'<a:defRPr sz="'.$sz.'" kern="1200"><a:solidFill><a:schemeClr val="tx1"/></a:solidFill>'
                .'<a:latin typeface="'.$latin.'"/><a:ea typeface=""/><a:cs typeface=""/></a:defRPr>'
                .'</a:lvl1pPr>';
        };

        return '<p:txStyles>'
            .'<a:titleStyle>'.$lv('4400', '+mj-lt').'</a:titleStyle>'
            .'<a:bodyStyle>'.$lv('1800', '+mn-lt').'</a:bodyStyle>'
            .'<a:otherStyle>'.$lv('1800', '+mn-lt').'</a:otherStyle>'
            .'</p:txStyles>';
    }

    /** PDF kosong berukuran A4, ditulis tangan (tak ada pustaka PDF di vendor). */
    public static function pdf(): string
    {
        $isi = "BT /F1 12 Tf 56 780 Td ( ) Tj ET\n";
        $bagian = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            "<< /Length ".strlen($isi)." >>\nstream\n".$isi."endstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $keluar = "%PDF-1.4\n";
        $posisi = [];
        foreach ($bagian as $i => $k) {
            $posisi[$i + 1] = strlen($keluar);
            $keluar .= ($i + 1)." 0 obj\n".$k."\nendobj\n";
        }

        $awalXref = strlen($keluar);
        $keluar .= "xref\n0 ".(count($bagian) + 1)."\n0000000000 65535 f \n";
        foreach ($posisi as $p) {
            $keluar .= str_pad((string) $p, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $keluar .= "trailer\n<< /Size ".(count($bagian) + 1)." /Root 1 0 R >>\n"
            ."startxref\n".$awalXref."\n%%EOF\n";

        return $keluar;
    }
}
