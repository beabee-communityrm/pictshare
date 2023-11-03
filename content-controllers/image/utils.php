<?php
// https://www.php.net/manual/en/function.iptcembed.php#113877

function transferIptcExif2File($srcfile, $destfile) {
    // Function transfers EXIF (APP1) and IPTC (APP13) from $srcfile and adds it to $destfile
    // JPEG file has format 0xFFD8 + [APP0] + [APP1] + ... [APP15] + <image data> where [APPi] are optional
    // Segment APPi (where i=0x0 to 0xF) has format 0xFFEi + 0xMM + 0xLL + <data> (where 0xMM is 
    //   most significant 8 bits of (strlen(<data>) + 2) and 0xLL is the least significant 8 bits 
    //   of (strlen(<data>) + 2)  

    if (file_exists($srcfile) && file_exists($destfile)) {
        $srcsize = @getimagesize($srcfile, $imageinfo);
        // Prepare EXIF data bytes from source file
        $exifdata = (is_array($imageinfo) && key_exists("APP1", $imageinfo)) ? $imageinfo['APP1'] : null;
        if ($exifdata) {
            $exiflength = strlen($exifdata) + 2;
            if ($exiflength > 0xFFFF) return false;
            // Construct EXIF segment
            $exifdata = chr(0xFF) . chr(0xE1) . chr(($exiflength >> 8) & 0xFF) . chr($exiflength & 0xFF) . $exifdata;
        }
        // Prepare IPTC data bytes from source file
        $iptcdata = (is_array($imageinfo) && key_exists("APP13", $imageinfo)) ? $imageinfo['APP13'] : null;
        if ($iptcdata) {
            $iptclength = strlen($iptcdata) + 2;
            if ($iptclength > 0xFFFF) return false;
            // Construct IPTC segment
            $iptcdata = chr(0xFF) . chr(0xED) . chr(($iptclength >> 8) & 0xFF) . chr($iptclength & 0xFF) . $iptcdata;
        }
        $destfilecontent = @file_get_contents($destfile);
        if (!$destfilecontent) return false;
        if (strlen($destfilecontent) > 0) {
            $destfilecontent = substr($destfilecontent, 2);
            $portiontoadd = chr(0xFF) . chr(0xD8);          // Variable accumulates new & original IPTC application segments
            $exifadded = !$exifdata;
            $iptcadded = !$iptcdata;

            while ((substr($destfilecontent, 0, 2) & 0xFFF0) === 0xFFE0) {
                $segmentlen = (substr($destfilecontent, 2, 2) & 0xFFFF);
                $iptcsegmentnumber = (substr($destfilecontent, 1, 1) & 0x0F);   // Last 4 bits of second byte is IPTC segment #
                if ($segmentlen <= 2) return false;
                $thisexistingsegment = substr($destfilecontent, 0, $segmentlen + 2);
                if ((1 <= $iptcsegmentnumber) && (!$exifadded)) {
                    $portiontoadd .= $exifdata;
                    $exifadded = true;
                    if (1 === $iptcsegmentnumber) $thisexistingsegment = '';
                }
                if ((13 <= $iptcsegmentnumber) && (!$iptcadded)) {
                    $portiontoadd .= $iptcdata;
                    $iptcadded = true;
                    if (13 === $iptcsegmentnumber) $thisexistingsegment = '';
                }
                $portiontoadd .= $thisexistingsegment;
                $destfilecontent = substr($destfilecontent, $segmentlen + 2);
            }
            if (!$exifadded) $portiontoadd .= $exifdata;  //  Add EXIF data if not added already
            if (!$iptcadded) $portiontoadd .= $iptcdata;  //  Add IPTC data if not added already
            $outputfile = fopen($destfile, 'w');
            if ($outputfile) return fwrite($outputfile, $portiontoadd . $destfilecontent); else return false;
        } else {
            return false;
        }
    } else {
        return false;
    }
}
