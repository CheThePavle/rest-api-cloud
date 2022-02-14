<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ObjectModel extends Model
{
    use HasFactory;

    protected $table = 'objects';

    protected $fillable = [
        'object_id',
        'parent_id',
        'name',
        'url',
        'id_author',
        'id_type'
    ];

    public function children(){
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getChildrenIds(&$arr){
        $children = $this->children;
        foreach ($children as $child) {
            Storage::deleteDirectory($child['object_id']);
            if (count($child->children) > 0)
                $child->getChildrenIds($arr);
            else
                array_push($arr, $child->id);
        }
    }



}
