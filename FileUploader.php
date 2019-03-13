<?php

namespace App\Services;

use App\Entity\ProductPhoto;
use Cocur\Slugify\Slugify;

class FileUploader
{
    private const ACCESS_PUBLIC = 'public';
    //private const ACCESS_PRIVATE = 'private';
    private const PRODUCT_DIR = 'product/';
    private const PHOTO_DIR = 'photo/';

    private $accessKey;
    private $secretKey;
    private $space;
    private $region;

    /**
     * @var \SpacesConnect
     */
    private $spacesConnect;
    /**
     * @var Slugify
     */
    private $slugify;

    /**
     * FileUploader constructor.
     * @throws
     */
    public function __construct()
    {
        $this->spacesConnect = new \SpacesConnect($_ENV['DO_ACCESS_KEY'], $_ENV['DO_SECRET_KEY'], $_ENV['DO_SPACE'], $_ENV['DO_REGION']);
        $this->slugify = new Slugify();
        $this->accessKey = $_ENV['DO_ACCESS_KEY'];
        $this->secretKey = $_ENV['DO_SECRET_KEY'];
        $this->space = $_ENV['DO_SPACE'];
        $this->region = $_ENV['DO_REGION'];
    }

    public function uploadProductPhoto(ProductPhoto $productPhoto)
    {
        $ex = explode('.', $productPhoto->getOriginalName());
        $saveAs = self::PRODUCT_DIR.
            $productPhoto->getProduct()->getId().'/'.
            self::PHOTO_DIR.
            $this->generateUniqueString().'.'.end($ex)
        ;
        $localPath = $productPhoto->getPath();
        $productPhoto->setPath($saveAs);

        return $this->uploadFile($localPath, $saveAs, $productPhoto->getMimeType());
    }

    public function uploadFile(string $localPath, string $saveAs, string $mimeType)
    {
        return $this->spacesConnect->UploadFile($localPath, self::ACCESS_PUBLIC, $saveAs, $mimeType);
    }

    public function deleteFile(string $path)
    {
        return $this->spacesConnect->DeleteObject($path);
    }

    private function generateUniqueString(): string
    {
        return trim(md5(uniqid()), "\r\n\t\0\x0B /");
    }
}
