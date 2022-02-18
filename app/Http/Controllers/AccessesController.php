<?php

namespace App\Http\Controllers;

use App\Models\Accesse;
use App\Models\ObjectModel;
use App\Models\User;
use Illuminate\Http\Request;
use Validator;


class AccessesController extends Controller
{
    public function checkAccess($idUser, $uniqIdObject, $type) {
        $object = ObjectModel::where('object_id', $uniqIdObject)
            ->where('id_type', $type)
            ->first();
        if (!$object) {
            return response()->json([
                'success'=>false,
                'message'=>'Файл или папка не найдены'
            ], 422);
        }

        if ($object->id_author != $idUser) {
            return response()->json([
                'success'=>false,
                'message'=>'Нет доступа к файлу или папке'
            ], 422);
        }

        return false;
    }

    public function resultAccesses ($email, $object, $delete = false) {
        $user = User::where('email', $email)
            ->first();
        if (!$user) {
            return response()->json([
                'success'=>false,
                'message'=>'Такой email не найден'
            ], 422);
        }

        if ($delete) {
            Accesse::where('id_object', $object['id'])
                ->where('id_co_author', $user->id)
                ->delete();
        } else {
            $checkAccess = Accesse::where('id_object', $object['id'])
                ->where('id_co_author', $user->id)
                ->first();
            if ($checkAccess) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Этот пользователь уже имеет доступ'
                ], 422);
            }
            Accesse::create([
                'id_object'=>$object['id'],
                'id_co_author'=>$user->id
            ]);
        }

        $resultAcceses = [];

        $findAuthor = User::where('id', $object['id_author'])
            ->first();
        array_push($resultAcceses, [
            'full_name'=>$findAuthor->first_name .' '. $findAuthor->last_name,
            'email'=>$findAuthor->email,
            'type'=>'author'
        ]);

        $accesses = Accesse::where('id_object', $object['id'])
            ->get();

        if (count($accesses) > 0) {
            foreach ($accesses as $access) {
                $findCoAuthor = User::where('id', $access['id_co_author'])
                    ->first();

                array_push($resultAcceses, [
                    'full_name'=>$findCoAuthor->first_name .' '. $findCoAuthor->last_name,
                    'email'=>$findCoAuthor->email,
                    'type'=>'co-author'
                ]);
            }
        }

        return response()->json($resultAcceses ,200);
    }

    public function addAccessFile (Request $request, $folder_id, $file_id)
    {
        $data = $request->all();
        $idUser = $data['idUser'];

        if ($folder_id != '0') {
            $fail = $this->checkAccess($idUser, $folder_id, 1);
            if ($fail) {
                return $fail;
            }
        }
        $fail = $this->checkAccess($idUser, $file_id, 2);
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

        $email = $data['email'];
        $file = ObjectModel::where('object_id', $file_id)
            ->first();

        return $this->resultAccesses($email, $file);
    }

    public function addAccessFolder (Request $request, $folder_id)
    {
        $data = $request->all();
        $idUser = $data['idUser'];

        $fail = $this->checkAccess($idUser, $folder_id, 1);
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

        $email = $data['email'];
        $folder = ObjectModel::where('object_id', $folder_id)
            ->first();

        return $this->resultAccesses($email, $folder);
    }

    public function deleteAccessFile (Request $request, $folder_id, $file_id)
    {
        $data = $request->all();
        $idUser = $data['idUser'];

        if ($folder_id != '0') {
            $fail = $this->checkAccess($idUser, $folder_id, 1);
            if ($fail) {
                return $fail;
            }
        }
        $fail = $this->checkAccess($idUser, $file_id, 2);
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

        $email = $data['email'];
        $file = ObjectModel::where('object_id', $file_id)
            ->first();

        return $this->resultAccesses($email, $file, true);
    }

    public function deleteAccessFolder (Request $request, $folder_id)
    {
        $data = $request->all();
        $idUser = $data['idUser'];

        $fail = $this->checkAccess($idUser, $folder_id, 1);
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

        $email = $data['email'];
        $folder = ObjectModel::where('object_id', $folder_id)
            ->first();

        return $this->resultAccesses($email, $folder, true);
    }

    public function disk (Request $request)
    {
        $data = $request->all();
        $idUser = $data['idUser'];

        $objects = ObjectModel::where('id_author', $idUser)
            ->get();

        $result = [];

        if (count($objects) > 0) {
            foreach ($objects as $object) {
                $resultAcceses = [];

                $findAuthor = User::where('id', $object['id_author'])
                    ->first();
                array_push($resultAcceses, [
                    'full_name'=>$findAuthor->first_name .' '. $findAuthor->last_name,
                    'email'=>$findAuthor->email,
                    'type'=>'author'
                ]);

                $accesses = Accesse::where('id_object', $object['id'])
                    ->get();

                if (count($accesses) > 0) {
                    foreach ($accesses as $access) {
                        $findCoAuthor = User::where('id', $access['id_co_author'])
                            ->first();

                        array_push($resultAcceses, [
                            'full_name'=>$findCoAuthor->first_name .' '. $findCoAuthor->last_name,
                            'email'=>$findCoAuthor->email,
                            'type'=>'co-author'
                        ]);
                    }
                }

                array_push($result, [
                    'type'=>$object->id_type == 1 ? 'folder' : 'file',
                    $object->id_type == 1 ? 'folder_id' : 'file_id'=>$object->object_id,
                    'name'=>$object->name,
                    'url'=>$object->url,
                    'acceses'=>$resultAcceses
                ]);
            }
        }

        return response()->json($result, 200);
    }

    public function shared (Request $request)
    {
        $data = $request->all();
        $idUser = $data['idUser'];

        $arrayAcceses = Accesse::where('id_co_author', $idUser)
            ->get();

        $result = [];

        if (count($arrayAcceses) > 0) {
            foreach ($arrayAcceses as $arryAccess) {
                $object = ObjectModel::where('id', $arryAccess['id_object'])
                    ->first();

                $resultAcceses = [];

                $findAuthor = User::where('id', $object['id_author'])
                    ->first();
                array_push($resultAcceses, [
                    'full_name'=>$findAuthor->first_name .' '. $findAuthor->last_name,
                    'email'=>$findAuthor->email,
                    'type'=>'author'
                ]);

                $accesses = Accesse::where('id_object', $object['id'])
                    ->get();

                if (count($accesses) > 0) {
                    foreach ($accesses as $access) {
                        $findCoAuthor = User::where('id', $access['id_co_author'])
                            ->first();

                        array_push($resultAcceses, [
                            'full_name'=>$findCoAuthor->first_name .' '. $findCoAuthor->last_name,
                            'email'=>$findCoAuthor->email,
                            'type'=>'co-author'
                        ]);
                    }
                }

                array_push($result, [
                    'type'=>$object->id_type == 1 ? 'folder' : 'file',
                    $object->id_type == 1 ? 'folder_id' : 'file_id'=>$object->object_id,
                    'name'=>$object->name,
                    'url'=>$object->url,
                    'acceses'=>$resultAcceses
                ]);
            }
        }

        return response()->json($result, 200);
    }
}
