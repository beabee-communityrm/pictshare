<?php 

class PdfController implements ContentController
{
    //returns all extensions registered by this type of content
    public function getRegisteredExtensions(){return array('pdf');}

    public function handleHash($hash,$url)
    {
        $path = ROOT.DS.'data'.DS.$hash.DS.$hash;

        if (file_exists($path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="'.basename($path).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($path));
            readfile($path);
        }
    }

    public function handleUpload($tmpfile,$hash=false)
    {
        if($hash===false)
        {
            $hash = getNewHash('pdf',6);
        }
        else
        {
            if(!endswith($hash,'.pdf'))
                $hash.='.pdf';
            if(isExistingHash($hash))
                return array('status'=>'err','hash'=>$hash,'reason'=>'Custom hash already exists');
        }

        storeFile($tmpfile,$hash,true);
        
        return array('status'=>'ok','hash'=>$hash,'url'=>URL.$hash);
    }
}
