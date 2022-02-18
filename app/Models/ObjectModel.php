<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Str;


class ObjectModel extends Model
{
    use HasFactory;

    protected $table = 'Objects';

    protected $fillable = [
        'object_id',
        'parent_id',
        'name',
        'extension',
        'url',
        'id_author',
        'id_type'
    ];

    public function getUniqId() {
        return Str::lower(Str::random(10));
    }

    public function children() {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function deleteChildrenFS($arr = []) {
        $children = $this->children;
        foreach ($children as $child) {
            Storage::delete(env('FOLDER_DOWLOAD').$child['object_id'].'.'.$child['extension']);
            if (count($child->children) > 0) {
                $child = $this->deleteChildrenFS($arr);
            } else {
                array_push($arr, $child->id);
            }
        }
    }
}
