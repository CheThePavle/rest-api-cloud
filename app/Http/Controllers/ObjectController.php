<?php

namespace App\Http\Controllers;

use App\Models\Accesse;
use App\Models\ObjectModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Validator;


class ObjectController extends Controller
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
            $access = Accesse::where('id_object', $object->id)
                ->where('id_co_author', $idUser)
                ->first();
            if (!$access) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Нет доступа к файлу или папке'
                ], 422);
            }
        }

        return false;
    }

    public function createFolder (Request $request) {
        $data = $request->all();
        $idUser = $data['idUser'];

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

        $name = $data['name'];
        $parent_id = $data['parent_id'];

        if ($parent_id == '0') {
            $checkUniq = ObjectModel::where('parent_id', NULL)
                ->where('name', $name)
                ->first();
            if ($checkUniq) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Имя папки уже занято'
                ], 422);
            }
        } else {
            $fail = $this->checkAccess($idUser, $parent_id, 1);
            if ($fail) {
                return $fail;
            }

            $folder = ObjectModel::where('object_id', $parent_id)
                ->first();

            $checkUniq = ObjectModel::where('parent_id', $folder->id)
                ->where('name', $name)
                ->first();
            if ($checkUniq) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Имя папки уже занято'
                ], 422);
            }
        }

        // $randUniq = new ObjectModel()
        $randUniq = ObjectModel::getUniqId();

        $parentFolderId = NULL;
        if (isset($folder)) {
            $parentFolderId = $folder->id;
        }

        ObjectModel::create([
            'object_id'=>$randUniq,
            'parent_id'=>$parentFolderId,
            'name'=>$name,
            'id_author'=>$idUser,
            'id_type'=>1
        ]);

        return response()->json([
            'success'=>true,
            'message'=>'Папка создана',
            'folder_id'=>$randUniq
        ], 200);
    }

    public function changeFolder (Request $request, $folder_id) {
        $data = $request->all();
        $idUser = $data['idUser'];
        $name = $request->get('name');
        $parent_id = $request->get('parent_id');

        if ($folder_id != '0') {
            $fail = $this->checkAccess($idUser, $folder_id, 1);
            if ($fail) {
                return $fail;
            }
        }

        $params = false;

        if ($name) {
            $params = true;
            ObjectModel::where('object_id', $folder_id)
                ->update(['name'=>$name]);
        }

        if ($parent_id == '0' || $parent_id) {
            $params = true;
            if ($parent_id != '0') {
                $fail = $this->checkAccess($idUser, $parent_id, 1);
                if ($fail) {
                    return $fail;
                }

                $parentFolder = ObjectModel::where('object_id', $parent_id)
                    ->first();
                ObjectModel::where('object_id', $folder_id)
                    ->update(['parent_id'=>$parentFolder->id]);
            } else {
                ObjectModel::where('object_id', $folder_id)
                    ->update(['parent_id'=>NULL]);
            }
        }

        if (!$params) {
            return response()->json([
                'success'=>false,
                'message'=>'Передайте name или parent_id'
            ], 422);
        }

        return response()->json([
            'success'=>true,
            'message'=>'Папка изменена'
        ], 200);
    }

    public function deleteFolder (Request $request, $folder_id) {
        $data = $request->all();
        $idUser = $data['idUser'];

        $fail = $this->checkAccess($idUser, $folder_id, 1);
        if ($fail) {
            return $fail;
        }

        $folder = ObjectModel::where('object_id', $folder_id)
            ->first();

        $folder->deleteChildrenFS();

        ObjectModel::where('id', $folder->id)
            ->delete();

        return response()->json([
            'success'=>true,
            'message'=>'Папка удалена'
        ], 200);
    }

    public function uploadeFiles (Request $request, $folder_id) {
        $data = $request->all();
        $idUser = $data['idUser'];

        if ($folder_id != '0') {
            $fail = $this->checkAccess($idUser, $folder_id, 1);
            if ($fail) {
                return $fail;
            }
        }

        if ($request->hasFile('files')) {
            $files = $request->file('files');

            if (!is_array($files)) {
                $files = [$files];
            }

            $result = [];
            foreach ($files as $file) {
                // $randUniq = new ObjectModel()
                $uniq = ObjectModel::getUniqId();
                $fullName = $file->getClientOriginalName();
                $fullName = explode('.', $fullName);
                $name = $fullName[0];
                $extension = $file->extension();

                $parentFolder = NULL;
                if ($folder_id != '0') {
                    $parentFolder = ObjectModel::where('object_id', $folder_id)
                        ->first();
                }

                $path = $file->storeAs(env('FOLDER_DOWLOAD'), $uniq.'.'.$extension);

                if ($path) {
                    array_push($result, [
                        'success'=>true,
                        'message'=>'Файл загружен',
                        'name'=>$name,
                        'url'=>env('APP_URL').'file/'.$uniq,
                        'file_id'=>$uniq
                    ]);

                    ObjectModel::create([
                        'object_id'=>$uniq,
                        'parent_id'=>$parentFolder == NULL ? NULL : $parentFolder->id,
                        'name'=>$name,
                        'extension'=>$extension,
                        'url'=>env('APP_URL').'file/'.$uniq,
                        'id_author'=>$idUser,
                        'id_type'=>2
                    ]);
                } else {
                    array_push($result, [
                        'success'=>false,
                        'message'=>'Файл не загружен',
                        'name'=>$name,
                    ]);
                }
            }
        } else {
            return response()->json([
                'success'=>false,
                'message'=>'Выберите файлы для параметра files'
            ], 422);
        }

        return $result;
    }

    public function changeFile (Request $request, $folder_id, $file_id) {
        $data = $request->all();
        $idUser = $data['idUser'];
        $name = $request->get('name');
        $parent_id = $request->get('parent_id');

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

        $params = false;

        if ($name) {
            $params = true;
            ObjectModel::where('object_id', $file_id)
                ->update(['name'=>$name]);
        }

        if ($parent_id == '0' || $parent_id) {
            $params = true;
            if ($parent_id != '0') {
                $fail = $this->checkAccess($idUser, $parent_id, 1);
                if ($fail) {
                    return $fail;
                }

                $parentFolder = ObjectModel::where('object_id', $parent_id)
                    ->first();
                ObjectModel::where('object_id', $file_id)
                    ->update(['parent_id'=>$parentFolder->id]);
            } else {
                ObjectModel::where('object_id', $file_id)
                    ->update(['parent_id'=>NULL]);
            }
        }

        if (!$params) {
            return response()->json([
                'success'=>false,
                'message'=>'Передайте name или parent_id'
            ], 422);
        }

        return response()->json([
            'success'=>true,
            'message'=>'Файл изменен'
        ], 200);
    }

    public function deleteFile (Request $request, $folder_id, $file_id) {
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

        $file = ObjectModel::where('object_id', $file_id)
            ->first();

        Storage::delete(env('FOLDER_DOWLOAD').$file_id.'.'.$file->extension);

        ObjectModel::where('id', $file->id)
            ->delete();

        return response()->json([
            'success'=>true,
            'message'=>'Файл удален'
        ], 200);
    }

    public function viewFolder (Request $request, $folder_id) {
        $data = $request->all();
        $idUser = $data['idUser'];

        $fail = $this->checkAccess($idUser, $folder_id, 1);
        if ($fail) {
            return $fail;
        }

        $folder = ObjectModel::where('object_id', $folder_id)
            ->first();

        $parentObjects = ObjectModel::where('parent_id', $folder->id)
            ->get();

        $result = [];

        if (count($parentObjects) > 0) {
            foreach ($parentObjects as $object) {
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

    public function downloadFile (Request $request, $file_id)
    {
        $data = $request->all();
        $idUser = $data['idUser'];

        $fail = $this->checkAccess($idUser, $file_id, 2);
        if ($fail) {
            return $fail;
        }

        $file = ObjectModel::where('object_id', $file_id)
            ->first();

        $pathFile = storage_path().'/app/public/'.env('FOLDER_DOWLOAD').$file_id.'.'.$file->extension;

        return response()->download($pathFile);
    }
}
