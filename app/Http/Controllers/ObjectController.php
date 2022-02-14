<?php

namespace App\Http\Controllers;

use App\Models\Access;
use App\Models\ObjectModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Validator;
use Str;


class ObjectController extends Controller
{
    public function createFolder (Request $request) {
        $getId = User::check($request->bearerToken());
        if (!$getId) {
            return response()->json([
                'success'=>false,
                'message'=>'Вы не авторизованы'
            ], 401);
        }

        $data = $request->all();

        $validate = Validator::make($data, [
            'name'=>'required',
            'parent_id'=>'required'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }

        $getObjectParentId = NULL;
        $getName = $data['name'];

        if ($data['parent_id'] == '0') {
            $checkUniqName = ObjectModel::where('parent_id', '=', NULL)
                ->where('name', '=', $getName)
                ->first();

            if ($checkUniqName) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Имя папки занято'
                ], 422);
            }
        } else {
            $findObject = ObjectModel::where('object_id', '=', $data['parent_id'])->first();
            if ($findObject) {
                $getObjectParentId = $findObject->id;
                $checkUniqName = ObjectModel::where('parent_id', '=', $getObjectParentId)
                    ->where('name', '=', $getName)
                    ->first();

                if ($checkUniqName) {
                    return response()->json([
                        'success'=>false,
                        'message'=>'Имя папки занято'
                    ], 422);
                }
            } else {
                return response()->json([
                    'success'=>false,
                    'message'=>'parent_id не найден'
                ], 422);
            }
        }

        $randId = Str::lower(Str::random(10));
        Storage::makeDirectory($randId);
        ObjectModel::create([
            'object_id'=>$randId,
            'parent_id'=>$getObjectParentId,
            'name'=>$getName,
            'url'=>env('APP_URL').'uploads/'.$randId,
            'id_author'=>$getId,
            'id_type'=>1
        ]);

        return response()->json([
            'success'=>true,
            'message'=>'Папка создана',
            'folder_id'=>$randId
        ], 200);
    }

    public function changeFolder (Request $request, $folder_id) {
        $getId = User::check($request->bearerToken());
        if (!$getId) {
            return response()->json([
                'success'=>false,
                'message'=>'Вы не авторизованы'
            ], 401);
        }

        $data = $request->all();
        if (!$data) {
            return response()->json([
                'success'=>false,
                'message'=>'Укажите name или parent_id'
            ], 422);
        }

        $getObject = ObjectModel::where('object_id', '=', $folder_id)
            ->where('id_type', '=', 1)
            ->first();

        if (!$getObject) {
            return response()->json([
                'success'=>false,
                'message'=>'Папка не найдена'
            ], 422);
        }

        $checkAuthor = ObjectModel::where('id_author', '=', $getId)
            ->where('id', '=', $getObject->id)
            ->where('id_type', '=', 1)
            ->first();

        if (!$checkAuthor) {
            $checkCoAuthor = Access::where('id_object', '=', $getObject->id)
                ->where('id_co_author', '=', $getId)
                ->first();
            if (!$checkCoAuthor) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Нет доступа'
                ], 403);
            }
        }

        // добавить name (i)
        if (isset($data['name'])) {
            ObjectModel::where('id', '=', $getObject->id)
                ->update(['name'=>$data['name']]);
        }

        if (isset($data['parent_id'])) {
            $parent_id = $data['parent_id'];
            if ($parent_id == '0') {
                ObjectModel::where('id', '=', $getObject->id)
                    ->update(['parent_id'=>NULL]);
            } else {
                $getObjectParent = ObjectModel::where('object_id', '=', $parent_id)
                    ->where('id_type', '=', 1)
                    ->first();
                if (!$getObjectParent) {
                    return response()->json([
                        'success'=>false,
                        'message'=>'parent_id не найден'
                    ], 403);
                }
                ObjectModel::where('id', '=', $getObject->id)
                    ->update(['parent_id'=>$getObjectParent->id]);
            }
        }

        return response()->json([
            'success'=>true,
            'message'=>'Редактирование папки успешно'
        ], 200);
    }

    public function deleteFolder (Request $request, $folder_id) {
        $getId = User::check($request->bearerToken());
        if (!$getId) {
            return response()->json([
                'success'=>false,
                'message'=>'Вы не авторизованы'
            ], 401);
        }

        $getObject = ObjectModel::where('object_id', '=', $folder_id)
            ->where('id_type', '=', 1)
            ->first();

        if (!$getObject) {
            return response()->json([
                'success'=>false,
                'message'=>'Папка не найдена'
            ], 422);
        }

        $checkAuthor = ObjectModel::where('id_author', '=', $getId)
            ->where('id', '=', $getObject->id)
            ->where('id_type', '=', 1)
            ->first();

        if (!$checkAuthor) {
            $checkCoAuthor = Access::where('id_object', '=', $getObject->id)
                ->where('id_co_author', '=', $getId)
                ->first();
            if (!$checkCoAuthor) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Нет доступа'
                ], 403);
            }
        }

        $objects = ObjectModel::where('id', $getObject->id)->first();
        $objects_ids = [];
        $objects->getChildrenIds($objects_ids);
        Storage::deleteDirectory($getObject->object_id);

        ObjectModel::where('id', '=', $getObject->id)
            ->delete();

        return response()->json([
            'success'=>true,
            'message'=>'Folder already deleted'
        ], 200);
    }
}
