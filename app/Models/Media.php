<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class Media extends Model {

    protected $table = "media";
    public $timestamps = false;

    const TYPE_DOCUMENT = "Document";
    const TYPE_VIDEO = "Video";
    const TYPE_PRESENTATION = "Presentation";
    const TYPE_IMAGE = "Image";

    public static function getUniqueFileName($filename, $ext) {
        $count = DB::table('media')
                ->where('file_name', $filename)
                ->count();
        if ($count > 0) {
            $newName = str_replace("." . $ext, "", $filename) . "-copy";
            return self::getUniqueFileName($newName . "." . $ext, $ext);
        }
        return $filename;
    }

    public static function getRecs($category, $searchFor = null) {
        $q = DB::table('media');
        $q->select('id', 'display_name', 'file_name', 'external_url', 'is_external', 'is_downloadable');
        $q->where('category', $category);
        $q->where('is_active', 1);
        if (!isset($searchFor)) {
            $q->where('display_name', 'like', '%' . $searchFor . '%');
        }
        return $q->get();
    }

    public static function getRecById($id) {
        return DB::table('media')
                        ->select('display_name', 'file_name', 'external_url', 'is_external', 'is_downloadable')
                        ->where('id', $id)
                        ->where('is_active', 1)
                        ->first();
    }

}
