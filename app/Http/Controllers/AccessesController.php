<?php

namespace App\Http\Controllers;

use App\Models\Access;
use App\Models\ObjectModel;
use App\Models\User;
use Illuminate\Http\Request;

class AccessesController extends Controller
{
    public function checkAuth ($idUser, $objectUniqId) {
        $getObject = ObjectModel::where('object_id', '=', $objectUniqId)
            ->first();
        if (!$getObject) {
            return response()->json([
                'success'=>false,
                'message'=>'Folder or file not found'
            ], 422);
        }

        $checkAuthor = ObjectModel::where('id', '=', $getObject->id)
            ->where('id_author', '=', $idUser)
            ->first();
        if (!$checkAuthor) {
            return response()->json([
                'success'=>false,
                'message'=>'No access object'
            ], 403);
        }

        return false;
    }

    public function resultAccesses ($email, $objectId, $objectIdAuthor, $delete) {
        $getAccessUser = User::where('email', '=', $email)
            ->first();
        if (!$getAccessUser) {
            return response()->json([
                'success'=>false,
                'message'=>'Пользователь с таким email не найден'
            ], 422);
        }
        $checkAccess = Access::where('id_object', '=', $objectId)
            ->where('id_co_author', '=', $getAccessUser->id)
            ->first();
        if ($checkAccess) {
            return response()->json([
                'success'=>false,
                'message'=>'Пользователь уже имеет доступ к этому файлу'
            ], 422);
        }

        if ($delete) {
            Access::where('id_object', '=', $objectId)
                ->where('id_co_author', '=', $getAccessUser->id)
                ->delete();
        } else {
            Access::create([
                'id_object'=>$objectId,
                'id_co_author'=>$getAccessUser->id
            ]);
        }


        $accesses = Access::where('id_object', '=', $objectId)
            ->get();

        $result = [];

        $findCreator = User::where('id', '=', $objectIdAuthor)
            ->first();
        array_push($result, [
            'full_name'=>$findCreator->first_name . ' ' . $findCreator->last_name,
            'email'=>$findCreator->email,
            'type'=>'author'
        ]);

        if (count($accesses) > 0) {
            foreach ($accesses as $access) {
                $idUser = $access['id_co_author'];
                $findUser = User::where('id', '=', $idUser)
                    ->select('first_name', 'last_name', 'email')
                    ->first();

                array_push($result, [
                    'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                    'email'=>$findUser->email,
                    'type'=>'co-author'
                ]);
            }
        }

        return response()->json($result, 200);
    }

    public function addAccessFile (Request $request, $folder_id, $file_id) {
        $data = $request->all();
        $getIdUser = $data['idUser'];

        $fail = $this->checkAuth($getIdUser, $folder_id);
        if ($fail) {
            return $fail;
        }
        $fail = $this->checkAuth($getIdUser, $file_id);
        if ($fail) {
            return $fail;
        }

        $validate = Validator::make($data, [
            'email'=>'required|email'
        ]);
        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }

        $getFile = ObjectModel::where('object_id', '=', $file_id)
            ->first();

        $email = $data['email'];

        return $this->resultAccesses($email, $getFile->id, $getFile->id_author, false);
    }

    public function addAccessFolder (Request $request, $folder_id) {
        $data = $request->all();
        $getIdUser = $data['idUser'];

        $fail = $this->checkAuth($getIdUser, $folder_id);
        if ($fail) {
            return $fail;
        }

        $validate = Validator::make($data, [
            'email'=>'required|email'
        ]);
        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }

        $getFolder = ObjectModel::where('object_id', '=', $folder_id)
            ->first();

        $email = $data['email'];

        return $this->resultAccesses($email, $getFolder->id, $getFolder->id_author, false);
    }

    public function deleteAccessFile (Request $request, $folder_id, $file_id) {
        $data = $request->all();
        $getIdUser = $data['idUser'];

        $fail = $this->checkAuth($getIdUser, $folder_id);
        if ($fail) {
            return $fail;
        }
        $fail = $this->checkAuth($getIdUser, $file_id);
        if ($fail) {
            return $fail;
        }

        $validate = Validator::make($data, [
            'email'=>'required|email'
        ]);
        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }

        $getFile = ObjectModel::where('object_id', '=', $folder_id)
            ->first();

        $email = $data['email'];

        return $this->resultAccesses($email, $getFile->id, $getFile->id_author, true);
    }

    public function deleteAccessFolder (Request $request, $folder_id) {
        $data = $request->all();
        $getIdUser = $data['idUser'];

        $fail = $this->checkAuth($getIdUser, $folder_id);
        if ($fail) {
            return $fail;
        }

        $validate = Validator::make($data, [
            'email'=>'required|email'
        ]);
        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }

        $getFolder = ObjectModel::where('object_id', '=', $folder_id)
            ->first();

        $email = $data['email'];

        return $this->resultAccesses($email, $getFolder->id, $getFolder->id_author, true);
    }

    public function myFolderAndFiles (Request $request) {
        $data = $request->all();
        $getIdUser = $data['idUser'];

        $getUser = User::where('id', '=', $getIdUser)
            ->first();

        $objects = ObjectModel::where('id_author', '=', $getIdUser)
            ->get();

        $result = [];
        foreach ($objects as $object) {
            $accessesResult = [];

            $accesses = Access::where('id_object', '=', $object['id'])
                ->get();

            array_push($accessesResult, [
                'full_name'=>$getUser->first_name . ' ' . $getUser->last_name,
                'email'=>$getUser->email,
                'type'=>'author'
            ]);

            if (count($accesses) > 0) {
                foreach ($accesses as $access) {
                    $idUser = $access['id_co_author'];
                    $findUser = User::where('id', '=', $idUser)
                        ->select('first_name', 'last_name', 'email')
                        ->first();

                    array_push($accessesResult, [
                        'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                        'email'=>$findUser->email,
                        'type'=>'co-author'
                    ]);
                }
            }

            array_push($result, [
                'type'=>$object['id_type'] == 1 ? 'folder' : 'file',
                $object['id_type'] == 1 ? 'folder_id' : 'file_id'=>$object['object_id'],
                'name'=>$object['name'],
                'url'=>$object['url'],
                'accesses'=>count($accessesResult) > 0 ? $accessesResult : 'all'
            ]);
        }
        return $result;
    }

    public function accessesFolderAndFiles (Request $request) {
        $data = $request->all();
        $getIdUser = $data['idUser'];

        $getObjectsAccesses = Access::where('id_co_author', '=', $getIdUser)
            ->get();

        $result = [];
        foreach ($getObjectsAccesses as $access) {
            $getObject = ObjectModel::where('id', '=', $access['id_object'])
                ->first();

            $accessesResult = [];

            $accesses = Access::where('id_object', '=', $getObject['id'])
                ->get();

            $findUser = User::where('id', '=', $getObject['id_author'])
                ->select('first_name', 'last_name', 'email')
                ->first();

            array_push($accessesResult, [
                'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                'email'=>$findUser->email,
                'type'=>'author'
            ]);

            if (count($accesses) > 0) {
                foreach ($accesses as $el) {
                    $idUser = $el['id_co_author'];
                    $findUser = User::where('id', '=', $idUser)
                        ->select('first_name', 'last_name', 'email')
                        ->first();

                    array_push($accessesResult, [
                        'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                        'email'=>$findUser->email,
                        'type'=>'co-author'
                    ]);
                }
            }

            array_push($result, [
                'type'=>$getObject['id_type'] == 1 ? 'folder' : 'file',
                $getObject['id_type'] == 1 ? 'folder_id' : 'file_id'=>$getObject['object_id'],
                'name'=>$getObject['name'],
                'url'=>$getObject['url'],
                'accesses'=>count($accessesResult) > 0 ? $accessesResult : 'all'
            ]);
        }

        return $result;
    }
}
