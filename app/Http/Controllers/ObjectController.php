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
//        $mystring = 'abc';
//        $findme   = 'bc';
//        return strpos($mystring, $findme);

    // проверка авторизации или доступа к объетку
    public function checkAuthAccess ($token, $id = false, $typeId = false) {
        $findUser = User::where('api_token', '=', $token)
            ->select('id')
            ->first();
        if (!$findUser) {
            return response()->json([
                'success'=>false,
                'message'=>'Вы не авторизованы'
            ], 401);
        }

        if ($id && $typeId) {
            $getObject = ObjectModel::where('object_id', '=', $id)
                ->where('id_type', '=', $typeId)
                ->select('id')
                ->first();
            if (!$getObject) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Папка или файл не найдены'
                ], 422);
            }

            $checkAuthor = ObjectModel::where('id', '=', $getObject->id)
                ->where('id_author', '=', $findUser->id)
                ->where('id_type', '=', $typeId)
                ->select('id')
                ->first();
            if (!$checkAuthor) {
                $checkCoAuthor = Access::where('id_object', '=', $getObject->id)
                    ->where('id_co_author', '=', $findUser->id)
                    ->select('id')
                    ->first();
                if (!$checkCoAuthor) {
                    return response()->json([
                        'success'=>false,
                        'message'=>'Нет доступа'
                    ], 403);
                }
            }

            return false;
        } else {
            return false;
        }
    }

    public function createFolder (Request $request) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token);
        if ($fail) {
            return $fail;
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

        $getUser = User::where('api_token', '=', $token)
            ->select('id')
            ->first();

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

        $randId = ObjectModel::genId();
        Storage::makeDirectory($randId);
        ObjectModel::create([
            'object_id'=>$randId,
            'parent_id'=>$getObjectParentId,
            'name'=>$getName,
            'url'=>env('APP_URL_UPLOADS').$randId,
            'id_author'=>$getUser->id,
            'id_type'=>1
        ]);

        return response()->json([
            'success'=>true,
            'message'=>'Папка создана',
            'folder_id'=>$randId
        ], 200);
    }

    public function changeFolder (Request $request, $folder_id) {
        $fail = $this->checkAuthAccess($request->bearerToken(), $folder_id, 1);
        if ($fail) {
            return $fail;
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
                $fail = $this->checkAuthAccess($request->bearerToken(), $parent_id, 1);
                if ($fail) {
                    return $fail;
                }

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
        $fail = $this->checkAuthAccess($request->bearerToken(), $folder_id, 1);
        if ($fail) {
            return $fail;
        }

        $getObject = ObjectModel::where('object_id', '=', $folder_id)
            ->where('id_type', '=', 1)
            ->first();

        $objects = ObjectModel::where('id', $getObject->id)->first();
        $objects_ids = [];
        $objects->getChildrenIds($objects_ids);
        Storage::deleteDirectory($folder_id);

        ObjectModel::where('id', '=', $getObject->id)
            ->delete();

        return response()->json([
            'success'=>true,
            'message'=>'Folder already deleted'
        ], 200);
    }

    public function uploadedFiles (Request $request, $folder_id) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token, $folder_id, 1);
        if ($fail) {
            return $fail;
        }

        if ($request->hasfile('files')) {
            $result = [];
            $files = $request->file('files');

            if (!is_array($files)) {
                $files = [$files];
            }

            $getObjectParent = ObjectModel::where('object_id', '=', $folder_id)
                ->select('id')
                ->first();

            $getUser = User::where('api_token', '=', $token)
                ->select('id')
                ->first();

            // добавить name (i)
            foreach ($files as $file) {
                $randId = ObjectModel::genId();
                $path = $file->storeAs($folder_id, $randId.'.'.$file->extension());

                if ($path) {
                    array_push($result, [
                        'success'=>true,
                        'message'=>'Файл загружен',
                        'name'=>$file->getClientOriginalName(),
                        'url'=>env('APP_URL_UPLOADS').$path,
                        'file_id'=>$randId
                    ]);

                    ObjectModel::create([
                        'object_id'=>$randId,
                        'parent_id'=>$getObjectParent->id,
                        'name'=>$file->getClientOriginalName(),
                        'url'=>env('APP_URL_UPLOADS').$path,
                        'id_author'=>$getUser->id,
                        'id_type'=>2,
                    ]);
                } else {
                    array_push($result, [
                        'success'=>false,
                        'message'=>'Файл не загружен',
                        'name'=>$file->getClientOriginalName()
                    ]);
                }
            }

            return response()->json($result, 200);
        } else {
            return response()->json([
                'success'=>false,
                'message'=>'Выберите файлы'
            ], 422);
        }
    }

    public function changeFile (Request $request, $folder_id, $file_id) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token, $folder_id, 1);
        if ($fail) {
            return $fail;
        }

        $getFile = ObjectModel::where('object_id', '=', $file_id)
            ->where('id_type', '=', 2)
            ->first();

        // добавить name (i)
        $data = $request->all();
        if (isset($data['name'])) {
            ObjectModel::where('id', '=', $getFile->id)
                ->update(['name'=>$data['name']]);
        }

        if (isset($data['folder_id'])) {
            $folder_parent_id = $data['folder_id'];
            if ($folder_parent_id == '0') {
                ObjectModel::where('id', '=', $getFile->id)
                    ->update(['parent_id'=>NULL]);
            } else {
                $fail = $this->checkAuthAccess($request->bearerToken(), $folder_parent_id, 1);
                if ($fail) {
                    return $fail;
                }

                $getFolderParent = ObjectModel::where('object_id', '=', $folder_parent_id)
                    ->where('id_type', '=', 1)
                    ->first();
                if (!$getFolderParent) {
                    return response()->json([
                        'success'=>false,
                        'message'=>'folder_id не найден'
                    ], 403);
                }
                ObjectModel::where('id', '=', $getFile->id)
                    ->update(['parent_id'=>$getFolderParent->id]);
            }

            $nameExt = explode('.' ,$getFile->name);
            $nameExt = $nameExt[count($nameExt) - 1];
            $nameExt = '.'.$nameExt;
            try {
                Storage::move($folder_id.'/'.$getFile->object_id.$nameExt, $folder_parent_id.'/'.$getFile->object_id.$nameExt);
            } catch (\Exception $e) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Что-то пошло не так, файл не найден или уже был перемещен'
                ], 422);
            }
        }

        if (!$data) {
            return response()->json([
                'success'=>false,
                'message'=>'Укажите name или folder_id'
            ], 422);
        }

        return response()->json([
            'success'=>true,
            'message'=>'Успешное изменение файла'
        ], 200);
    }

    public function deleteFile (Request $request, $folder_id, $file_id) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token, $folder_id, 1);
        if ($fail) {
            return $fail;
        }

        $getFile = ObjectModel::where('object_id', '=', $file_id)
            ->where('id_type', '=', 2)
            ->first();

        ObjectModel::where('id', '=', $getFile->id)
            ->delete();

        $nameExt = explode('.' ,$getFile->name);
        $nameExt = $nameExt[count($nameExt) - 1];
        $nameExt = '.'.$nameExt;
        Storage::delete($folder_id.'/'.$file_id.$nameExt);

        return response()->json([
            'success'=>true,
            'message'=>'Успешное удаление файла'
        ], 200);
    }

    public function showFolder (Request $request, $folder_id) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token, $folder_id, 1);
        if ($fail) {
            return $fail;
        }

        $getFolder = ObjectModel::where('object_id', '=', $folder_id)
            ->where('id_type', '=', 1)
            ->select('id', 'id_author')
            ->first();

        $result = ObjectModel::where('parent_id', '=', $getFolder->id)
            ->get();

        $json = [];
        foreach ($result as $item) {
            $accesses = Access::where('id_object', '=', $item['id'])
                ->get();

            $jsonAccesses = [];

            $findCreator = User::where('id', '=', $getFolder->id_author)
                ->select('first_name', 'last_name', 'email')
                ->first();
            array_push($jsonAccesses, [
                'full_name'=>$findCreator->first_name . ' ' . $findCreator->last_name,
                'email'=>$findCreator->email,
                'type'=>'author'
            ]);

            if (count($accesses) > 0) {
                foreach ($accesses as $el) {
                    $idUser = $el['id_co_author'];
                    $findUser = User::where('id', '=', $idUser)
                        ->select('first_name', 'last_name', 'email')
                        ->first();

                    array_push($jsonAccesses, [
                        'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                        'email'=>$findUser->email,
                        'type'=>'co-author'
                    ]);
                }
            }

            array_push($json, [
                'type'=>$item['id_type'] == 1 ? 'folder' : 'file',
                $item['id_type'] == 1 ? 'folder_id' : 'file_id'=>$item['object_id'],
                'name'=>$item['name'],
                'url'=>$item['url'],
                'accesses'=>count($jsonAccesses) > 0 ? $jsonAccesses : 'all'
            ]);
        }
        return $json;
    }

    public function downloadFile (Request $request, $file_id) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token, $file_id, 2);
        if ($fail) {
            return $fail;
        }

        $getFile = ObjectModel::where('object_id', '=', $file_id)
            ->where('id_type', '=', 2)
            ->select('id', 'name', 'parent_id')
            ->first();
        $fileName = $getFile->name;

        $getParentFolder = ObjectModel::where('id', '=', $getFile->parent_id)
            ->where('id_type', '=', 1)
            ->select('id', 'object_id')
            ->first();
        $folderParent = '';
        if ($getParentFolder) {
            $folderParent = $getParentFolder->object_id.'/';
        }

        $nameExt = explode('.' , $fileName);
        $nameExt = $nameExt[count($nameExt) - 1];
        $nameExt = '.'.$nameExt;

        $pathToFile = storage_path()."/app/public/".$folderParent.$file_id.$nameExt;
        return response()->download($pathToFile);
    }

    public function addAccessFile (Request $request, $folder_id, $file_id) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token, $folder_id, 1);
        if ($fail) {
            return $fail;
        }

        $data = $request->all();
        $validate = Validator::make($data, [
            'email'=>'required|email'
        ]);
        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }

        $getUser = User::where('api_token', '=', $token)
            ->select('id')
            ->first();
        $checkAuthorFile = ObjectModel::where('object_id', '=', $file_id)
            ->where('id_author', '=', $getUser->id)
            ->where('id_type', '=', 2)
            ->select('id', 'id_author')
            ->first();
        if (!$checkAuthorFile) {
            return response()->json([
                'success'=>false,
                'message'=>'Нет доступа к файлу'
            ], 422);
        }

        $email = $data['email'];
        $getAccessUser = User::where('email', '=', $email)
            ->select('id')
            ->first();
        if (!$getAccessUser) {
            return response()->json([
                'success'=>false,
                'message'=>'Пользователь с таким email не найден'
            ], 422);
        }
        $checkAccess = Access::where('id_object', '=', $checkAuthorFile->id)
            ->where('id_co_author', '=', $getAccessUser->id)
            ->first();
        if ($checkAccess) {
            return response()->json([
                'success'=>false,
                'message'=>'Пользователь уже имеет доступ к этому файлу'
            ], 422);
        }

        Access::create([
            'id_object'=>$checkAuthorFile->id,
            'id_co_author'=>$getAccessUser->id
        ]);

        $accesses = Access::where('id_object', '=', $checkAuthorFile->id)
            ->get();
        $jsonAccesses = [];

        $findCreator = User::where('id', '=', $checkAuthorFile->id_author)
            ->select('first_name', 'last_name', 'email')
            ->first();

        array_push($jsonAccesses, [
            'full_name'=>$findCreator->first_name . ' ' . $findCreator->last_name,
            'email'=>$findCreator->email,
            'type'=>'author'
        ]);

        if (count($accesses) > 0) {
            foreach ($accesses as $el) {
                $idUser = $el['id_co_author'];
                $findUser = User::where('id', '=', $idUser)
                    ->select('first_name', 'last_name', 'email')
                    ->first();

                array_push($jsonAccesses, [
                    'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                    'email'=>$findUser->email,
                    'type'=>'co-author'
                ]);
            }
        }

        return response()->json($jsonAccesses, 200);
    }

    public function addAccessFolder (Request $request, $folder_id) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token);
        if ($fail) {
            return $fail;
        }

        $data = $request->all();
        $validate = Validator::make($data, [
            'email'=>'required|email'
        ]);
        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }

        $getUser = User::where('api_token', '=', $token)
            ->select('id')
            ->first();
        $checkAuthorFolder = ObjectModel::where('object_id', '=', $folder_id)
            ->where('id_author', '=', $getUser->id)
            ->where('id_type', '=', 1)
            ->select('id', 'id_author')
            ->first();
        if (!$checkAuthorFolder) {
            return response()->json([
                'success'=>false,
                'message'=>'Нет доступа к папке'
            ], 422);
        }

        $email = $data['email'];
        $getAccessUser = User::where('email', '=', $email)
            ->select('id')
            ->first();
        if (!$getAccessUser) {
            return response()->json([
                'success'=>false,
                'message'=>'Пользователь с таким email не найден'
            ], 422);
        }
        $checkAccess = Access::where('id_object', '=', $checkAuthorFolder->id)
            ->where('id_co_author', '=', $getAccessUser->id)
            ->first();
        if ($checkAccess) {
            return response()->json([
                'success'=>false,
                'message'=>'Пользователь уже имеет доступ к этой папке'
            ], 422);
        }

        Access::create([
            'id_object'=>$checkAuthorFolder->id,
            'id_co_author'=>$getAccessUser->id
        ]);

        $accesses = Access::where('id_object', '=', $checkAuthorFolder->id)
            ->get();
        $jsonAccesses = [];

        $findCreator = User::where('id', '=', $checkAuthorFolder->id_author)
            ->select('first_name', 'last_name', 'email')
            ->first();

        array_push($jsonAccesses, [
            'full_name'=>$findCreator->first_name . ' ' . $findCreator->last_name,
            'email'=>$findCreator->email,
            'type'=>'author'
        ]);

        if (count($accesses) > 0) {
            foreach ($accesses as $el) {
                $idUser = $el['id_co_author'];
                $findUser = User::where('id', '=', $idUser)
                    ->select('first_name', 'last_name', 'email')
                    ->first();

                array_push($jsonAccesses, [
                    'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                    'email'=>$findUser->email,
                    'type'=>'co-author'
                ]);
            }
        }

        return response()->json($jsonAccesses, 200);
    }

    public function deleteAccessFile (Request $request, $folder_id, $file_id) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token, $folder_id, 1);
        if ($fail) {
            return $fail;
        }

        $data = $request->all();
        $validate = Validator::make($data, [
            'email'=>'required|email'
        ]);
        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }

        $getUser = User::where('api_token', '=', $token)
            ->select('id')
            ->first();
        $checkAuthorFile = ObjectModel::where('object_id', '=', $file_id)
            ->where('id_author', '=', $getUser->id)
            ->where('id_type', '=', 2)
            ->select('id', 'id_author')
            ->first();
        if (!$checkAuthorFile) {
            return response()->json([
                'success'=>false,
                'message'=>'Нет доступа к файлу'
            ], 422);
        }

        $email = $data['email'];
        $getAccessUser = User::where('email', '=', $email)
            ->select('id')
            ->first();
        if (!$getAccessUser) {
            return response()->json([
                'success'=>false,
                'message'=>'Пользователь с таким email не найден'
            ], 422);
        }

        Access::where('id_object', '=', $checkAuthorFile->id)
            ->where('id_co_author', '=', $getAccessUser->id)
            ->delete();

        $accesses = Access::where('id_object', '=', $checkAuthorFile->id)
            ->get();
        $jsonAccesses = [];

        $findCreator = User::where('id', '=', $checkAuthorFile->id_author)
            ->select('first_name', 'last_name', 'email')
            ->first();

        array_push($jsonAccesses, [
            'full_name'=>$findCreator->first_name . ' ' . $findCreator->last_name,
            'email'=>$findCreator->email,
            'type'=>'author'
        ]);

        if (count($accesses) > 0) {
            foreach ($accesses as $el) {
                $idUser = $el['id_co_author'];
                $findUser = User::where('id', '=', $idUser)
                    ->select('first_name', 'last_name', 'email')
                    ->first();

                array_push($jsonAccesses, [
                    'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                    'email'=>$findUser->email,
                    'type'=>'co-author'
                ]);
            }
        }

        return response()->json($jsonAccesses, 200);
    }

    public function deleteAccessFolder (Request $request, $folder_id) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token, $folder_id, 1);
        if ($fail) {
            return $fail;
        }

        $data = $request->all();
        $validate = Validator::make($data, [
            'email'=>'required|email'
        ]);
        if ($validate->fails()) {
            return response()->json([
                'success'=>false,
                'message'=>$validate->errors()
            ], 422);
        }

        $getUser = User::where('api_token', '=', $token)
            ->select('id')
            ->first();
        $checkAuthorFolder = ObjectModel::where('object_id', '=', $folder_id)
            ->where('id_author', '=', $getUser->id)
            ->where('id_type', '=', 1)
            ->select('id', 'id_author')
            ->first();
        if (!$checkAuthorFolder) {
            return response()->json([
                'success'=>false,
                'message'=>'Нет доступа к файлу'
            ], 422);
        }

        $email = $data['email'];
        $getAccessUser = User::where('email', '=', $email)
            ->select('id')
            ->first();
        if (!$getAccessUser) {
            return response()->json([
                'success'=>false,
                'message'=>'Пользователь с таким email не найден'
            ], 422);
        }

        Access::where('id_object', '=', $checkAuthorFolder->id)
            ->where('id_co_author', '=', $getAccessUser->id)
            ->delete();

        $accesses = Access::where('id_object', '=', $checkAuthorFolder->id)
            ->get();
        $jsonAccesses = [];

        $findCreator = User::where('id', '=', $checkAuthorFolder->id_author)
            ->select('first_name', 'last_name', 'email')
            ->first();

        array_push($jsonAccesses, [
            'full_name'=>$findCreator->first_name . ' ' . $findCreator->last_name,
            'email'=>$findCreator->email,
            'type'=>'author'
        ]);

        if (count($accesses) > 0) {
            foreach ($accesses as $el) {
                $idUser = $el['id_co_author'];
                $findUser = User::where('id', '=', $idUser)
                    ->select('first_name', 'last_name', 'email')
                    ->first();

                array_push($jsonAccesses, [
                    'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                    'email'=>$findUser->email,
                    'type'=>'co-author'
                ]);
            }
        }

        return response()->json($jsonAccesses, 200);
    }

    public function myFolderAndFiles (Request $request) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token);
        if ($fail) {
            return $fail;
        }

        $getUser = User::where('api_token', '=', $token)
            ->select('id', 'first_name', 'last_name', 'email')
            ->first();

        $result = ObjectModel::where('id_author', '=', $getUser->id)
            ->get();

        $json = [];
        foreach ($result as $item) {
            $accesses = Access::where('id_object', '=', $item['id'])
                ->get();

            $jsonAccesses = [];

            array_push($jsonAccesses, [
                'full_name'=>$getUser->first_name . ' ' . $getUser->last_name,
                'email'=>$getUser->email,
                'type'=>'author'
            ]);

            if (count($accesses) > 0) {
                foreach ($accesses as $el) {
                    $idUser = $el['id_co_author'];
                    $findUser = User::where('id', '=', $idUser)
                        ->select('first_name', 'last_name', 'email')
                        ->first();

                    array_push($jsonAccesses, [
                        'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                        'email'=>$findUser->email,
                        'type'=>'co-author'
                    ]);
                }
            }

            array_push($json, [
                'type'=>$item['id_type'] == 1 ? 'folder' : 'file',
                $item['id_type'] == 1 ? 'folder_id' : 'file_id'=>$item['object_id'],
                'name'=>$item['name'],
                'url'=>$item['url'],
                'accesses'=>count($jsonAccesses) > 0 ? $jsonAccesses : 'all'
            ]);
        }
        return $json;
    }

    public function accessesFolderAndFiles (Request $request) {
        $token = $request->bearerToken();
        $fail = $this->checkAuthAccess($token);
        if ($fail) {
            return $fail;
        }

        $getUser = User::where('api_token', '=', $token)
            ->select('id', 'first_name', 'last_name', 'email')
            ->first();

        $getObjectsAccesses = Access::where('id_co_author', '=', $getUser->id)
            ->select('id_object')
            ->get();

        $json = [];
        foreach ($getObjectsAccesses as $accesses) {
            $result = ObjectModel::where('id', '=', $accesses['id_object'])
                ->get();

            foreach ($result as $item) {
                $accesses = Access::where('id_object', '=', $item['id'])
                    ->get();

                $jsonAccesses = [];

                $findUser = User::where('id', '=', $item['id_author'])
                    ->select('first_name', 'last_name', 'email')
                    ->first();

                array_push($jsonAccesses, [
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

                        array_push($jsonAccesses, [
                            'full_name'=>$findUser->first_name . ' ' . $findUser->last_name,
                            'email'=>$findUser->email,
                            'type'=>'co-author'
                        ]);
                    }
                }

                array_push($json, [
                    'type'=>$item['id_type'] == 1 ? 'folder' : 'file',
                    $item['id_type'] == 1 ? 'folder_id' : 'file_id'=>$item['object_id'],
                    'name'=>$item['name'],
                    'url'=>$item['url'],
                    'accesses'=>count($jsonAccesses) > 0 ? $jsonAccesses : 'all'
                ]);
            }
        }

        return $json;
    }
}
