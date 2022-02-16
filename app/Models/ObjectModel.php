<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Str;


class ObjectModel extends Model
{
    use HasFactory;

    protected $table = 'objects';

    protected $fillable = [
        'object_id',
        'parent_id',
        'name',
        'extension',
        'url',
        'id_author',
        'id_type'
    ];

    public function children(){
        return $this->hasMany(self::class, 'parent_id');
    }

    public function deleteChildrenFolderFS(&$arr){
        $children = $this->children;
        foreach ($children as $child) {
            Storage::deleteDirectory($child['object_id']);
            if (count($child->children) > 0)
                $child->deleteChildrenFolderFS($arr);
            else
                array_push($arr, $child->id);
        }
    }

    public function genUniqId() {
        return Str::lower(Str::random(10));
    }
}
