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
    public function checkAccess ($idUser, $objectUniqId) {
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
            $checkCoAuthor = Access::where('id_object', '=', $getObject->id)
                ->where('id_co_author', '=', $idUser)
                ->first();
            if (!$checkCoAuthor) {
                return response()->json([
                    'success'=>false,
                    'message'=>'No access object'
                ], 403);
            }
        }

        return false;
    }

    public function renameObject ($uniqParentId, $name, $type, $uploadToFolder = false) {
        if (!$uniqParentId) {
            $objects = ObjectModel::where('parent_id', '=', NULL)
                ->where('id_type', '=', $type)
                ->get();
            if (count($objects) > 0) {
                $counter = 0;
                foreach ($objects as $object) {
                    if ($object['name'] == $name) {
                        $counter = $counter + 1;
                    }
                    if ($object['name'] == $name . ' (' . $counter. ')') {
                        $counter = $counter + 1;
                    }
                }
                if ($counter == 0) {
                    return $name;
                } else {
                    return $name . ' ('.$counter.')';
                }
            }

            return $name;
        } else {
            if ($uploadToFolder) {
                $object = ObjectModel::where('object_id', '=', $uniqParentId)
                    ->where('id_type', '=', 1)
                    ->first();
                $objectsParent = ObjectModel::where('parent_id', '=', $object->id)
                    ->where('id_type', '=', $type)
                    ->get();
            } else {
                $object = ObjectModel::where('object_id', '=', $uniqParentId)
                    ->where('id_type', '=', $type)
                    ->first();
                $objectsParent = ObjectModel::where('parent_id', '=', $object->parent_id)
                    ->where('id_type', '=', $type)
                    ->get();
            }

            $counter = 0;
            foreach ($objectsParent as $object) {
                if ($object['name'] == $name) {
                    $counter = $counter + 1;
                }
                if ($object['name'] == $name . ' (' . $counter. ')') {
                    $counter = $counter + 1;
                }
            }

            if ($counter == 0) {
                return $name;
            } else {
                return $name . ' ('.$counter.')';
            }
        }
    }

    public function createFolder (Request $request) {
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

        $getIdUser = $data['idUser'];
        $getName = $data['name'];
        $getUniqParentId = $data['parent_id'];
        $getFolderParentId = NULL;

        if ($getUniqParentId == '0') {
            $checkUniqName = ObjectModel::where('parent_id', '=', $getFolderParentId)
                ->where('name', '=', $getName)
                ->first();
            if ($checkUniqName) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Имя папки занято'
                ], 422);
            }
        } else {
            $fail = $this->checkAccess($getIdUser, $getUniqParentId);
            if ($fail) {
                return $fail;
            }

            $getFolder = ObjectModel::where('object_id', '=', $getUniqParentId)
                ->first();
            $getFolderParentId = $getFolder->id;

            $checkUniqName = ObjectModel::where('parent_id', '=', $getFolderParentId)
                ->where('name', '=', $getName)
                ->first();
            if ($checkUniqName) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Имя папки уже занято'
                ], 422);
            }
        }

        $getUniqId = ObjectModel::genUniqId();
        Storage::makeDirectory(env('FOLDER_UPLOADS').$getUniqId);
        ObjectModel::create([
            'object_id'=>$getUniqId,
            'parent_id'=>$getFolderParentId,
            'name'=>$getName,
            'url'=>env('APP_URL_UPLOADS').$getUniqId,
            'id_author'=>$getIdUser,
            'id_type'=>1
        ]);

        return response()->json([
            'success'=>true,
            'message'=>'Папка создана',
            'folder_id'=>$getUniqId
        ], 200);
    }

    public function changeFolder (Request $request, $folder_id) {
        $data = $request->all();
        if (!$data) {
            return response()->json([
                'success'=>false,
                'message'=>'Укажите name или parent_id'
            ], 422);
        }

        $getIdUser = $data['idUser'];
        $getName = isset($data['name']) ? $data['name'] : false;
        $getUniqParentId = isset($data['parent_id']) ? $data['parent_id'] : false;

        $fail = $this->checkAccess($getIdUser, $folder_id);
        if ($fail) {
            return $fail;
        }

        $getFolder = ObjectModel::where('object_id', '=', $folder_id)
            ->first();

        if ($getName) {
            if ($getUniqParentId) {
                if ($getUniqParentId == '0') {
                    $getName = $this->renameObject(false, $getName, 1);
                } else {
                    $fail = $this->checkAccess($getIdUser, $getUniqParentId);
                    if ($fail) {
                        return $fail;
                    }
                    $getName = $this->renameObject($getUniqParentId, $getName, 1);
                }
            } else {
                $getName = $this->renameObject($folder_id, $getName, 1);
            }
            ObjectModel::where('id', '=', $getFolder->id)
                ->update(['name'=>$getName]);
        }

        if ($getUniqParentId == '0' || $getUniqParentId) {
            if ($getUniqParentId == '0') {
                if (!$getName) {
                    $getName = ObjectModel::where('id', '=', $getFolder->id)
                        ->first();
                    $getName = $this->renameObject(false, $getName->name, 1);

                    ObjectModel::where('id', '=', $getFolder->id)
                        ->update([
                            'parent_id'=>NULL,
                            'name'=>$getName
                        ]);
                } else {
                    ObjectModel::where('id', '=', $getFolder->id)
                        ->update(['parent_id'=>NULL]);
                }
            } else {
                $fail = $this->checkAccess($getIdUser, $getUniqParentId);
                if ($fail) {
                    return $fail;
                }

                $getFolderParent = ObjectModel::where('object_id', '=', $getUniqParentId)
                    ->first();
                ObjectModel::where('id', '=', $getFolder->id)
                    ->update(['parent_id'=>$getFolderParent->id]);
            }
        }

        return response()->json([
            'success'=>true,
            'message'=>'Редактирование папки успешно'
        ], 200);
    }

    public function deleteFolder (Request $request, $folder_id) {
        $data = $request->all();
        $getIdUser = $data['idUser'];

        $fail = $this->checkAccess($getIdUser, $folder_id);
        if ($fail) {
            return $fail;
        }

        $getFolderAndDeleteFS = ObjectModel::where('object_id', '=', $folder_id)
            ->first();
        $middleObjects = [];
        $getFolderAndDeleteFS->deleteChildrenFolderFS($middleObjects);

        Storage::deleteDirectory(env('FOLDER_UPLOADS').$folder_id);
        ObjectModel::where('id', '=', $getFolderAndDeleteFS->id)
            ->delete();

        return response()->json([
            'success'=>true,
            'message'=>'Folder already deleted'
        ], 200);
    }

    public function uploadedFiles (Request $request, $folder_id) {
        $data = $request->all();
        $getIdUser = $data['idUser'];

        $folder_id = isset($folder_id) ? $folder_id : false;
        if ($folder_id == '0' || $folder_id) {
            if ($folder_id == '0') {
                $folder_id = NULL;
            } else {
                $fail = $this->checkAccess($getIdUser, $folder_id);
                if ($fail) {
                    return $fail;
                }
            }
        } else {
            return response()->json([
                'success'=>false,
                'message'=>'folder_id не передан'
            ], 422);
        }

        if ($request->hasfile('files')) {
            $result = [];
            $files = $request->file('files');

            if (!is_array($files)) {
                $files = [$files];
            }

            $getFolderParent = NULL;
            if ($folder_id != '0') {
                $getFolderParent = ObjectModel::where('object_id', '=', $folder_id)
                    ->first();
            }

            foreach ($files as $file) {
                $getUniqId = ObjectModel::genUniqId();
                $nameFile = explode('.' , $file->getClientOriginalName());
                $nameFile = $nameFile[0];

                if ($folder_id) {
                    $path = $file->storeAs(env('FOLDER_UPLOADS').$folder_id, $getUniqId.'.'.$file->extension());
                    $name = $this->renameObject($folder_id, $nameFile, 2, true);
                } else {
                    $path = $file->storeAs(env('FOLDER_UPLOADS'), $getUniqId.'.'.$file->extension());
                    $name = $this->renameObject(false, $nameFile, 2);
                }

                if ($path) {
                    array_push($result, [
                        'success'=>true,
                        'message'=>'Файл загружен',
                        'name'=>$name,
                        'url'=>env('APP_URL_UPLOADS').$path,
                        'file_id'=>$getUniqId
                    ]);

                    ObjectModel::create([
                        'object_id'=>$getUniqId,
                        'parent_id'=>$getFolderParent == NULL ? NULL : $getFolderParent->id,
                        'name'=>$name,
                        'extension'=>$file->extension(),
                        'url'=>env('APP_URL_UPLOADS').$path,
                        'id_author'=>$getIdUser,
                        'id_type'=>2,
                    ]);
                } else {
                    array_push($result, [
                        'success'=>false,
                        'message'=>'Файл не загружен',
                        'name'=>$name
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
        $data = $request->all();
        $getIdUser = $data['idUser'];
        $getName = isset($data['name']) ? $data['name'] : false;
        $getUniqParentFolder = isset($data['folder_id']) ? $data['folder_id'] : false;

        if ($folder_id != '0') {
            $fail = $this->checkAccess($getIdUser, $folder_id);
            if ($fail) {
                return $fail;
            }
        }

        $fail = $this->checkAccess($getIdUser, $file_id);
        if ($fail) {
            return $fail;
        }

        $getFile = ObjectModel::where('object_id', '=', $file_id)
            ->first();

        if ($getName) {
            if ($getUniqParentFolder) {
                $fail = $this->checkAccess($getIdUser, $getUniqParentFolder);
                if ($fail) {
                    return $fail;
                }
                $getName = $this->renameObject($getUniqParentFolder, $getName, 2);
            } else {
                $getName = $this->renameObject(false, $getName, 2);
            }

            ObjectModel::where('id', '=', $getFile->id)
                ->update(['name'=>$getName]);
        }

        if ($getUniqParentFolder == '0' || $getUniqParentFolder) {
            if ($getUniqParentFolder == '0') {
                ObjectModel::where('id', '=', $getFile->id)
                    ->update(['parent_id'=>NULL]);
            } else {
                $fail = $this->checkAccess($getIdUser, $getUniqParentFolder);
                if ($fail) {
                    return $fail;
                }

                $getFolder = ObjectModel::where('object_id', '=', $getUniqParentFolder)
                    ->first();
                ObjectModel::where('id', '=', $getFile->id)
                    ->update(['parent_id'=>$getFolder->id]);
            }

            $folder_id = $folder_id == '0' ? '' : $folder_id.'/';
            $extension = $getFile->extension;
            try {
                Storage::move(
                    env('FOLDER_UPLOADS').$folder_id.$getFile->object_id.'.'.$extension,
                    env('FOLDER_UPLOADS').$getUniqParentFolder.'/'.$getFile->object_id.'.'.$extension
                );
            } catch (\Exception $e) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Файл не найден в uploads или уже был перемещен'
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
        $data = $request->all();
        $getIdUser = $data['idUser'];

        if ($folder_id != '0') {
            $fail = $this->checkAccess($getIdUser, $folder_id);
            if ($fail) {
                return $fail;
            }
        }
        $fail = $this->checkAccess($getIdUser, $file_id);
        if ($fail) {
            return $fail;
        }

        $getFile = ObjectModel::where('object_id', '=', $file_id)
            ->first();

        ObjectModel::where('id', '=', $getFile->id)
            ->delete();

        $extension = $getFile->extension;
        if ($folder_id == '0') {
            Storage::delete(env('FOLDER_UPLOADS').$file_id.'.'.$extension);
        } else {
            Storage::delete(env('FOLDER_UPLOADS').$folder_id.'/'.$file_id.'.'.$extension);
        }


        return response()->json([
            'success'=>true,
            'message'=>'Успешное удаление файла'
        ], 200);
    }

    public function showFolder (Request $request, $folder_id) {
        $data = $request->all();
        $getIdUser = $data['idUser'];

        $fail = $this->checkAccess($getIdUser, $folder_id);
        if ($fail) {
            return $fail;
        }

        $getFolder = ObjectModel::where('object_id', '=', $folder_id)
            ->first();

        $getParentObjects = ObjectModel::where('parent_id', '=', $getFolder->id)
            ->get();

        $result = [];
        foreach ($getParentObjects as $object) {
            $resultAccesses = [];

            $accesses = Access::where('id_object', '=', $object['id'])
                ->get();

            $findCreator = User::where('id', '=', $getFolder->id_author)
                ->first();
            array_push($resultAccesses, [
                'full_name'=>$findCreator->first_name . ' ' . $findCreator->last_name,
                'email'=>$findCreator->email,
                'type'=>'author'
            ]);

            if (count($accesses) > 0) {
                foreach ($accesses as $access) {
                    $idUser = $access['id_co_author'];
                    $findUser = User::where('id', '=', $idUser)
                        ->first();

                    array_push($resultAccesses, [
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
                'accesses'=>count($resultAccesses) > 0 ? $resultAccesses : 'all'
            ]);
        }
        return $result;
    }

    public function downloadFile (Request $request, $file_id) {
        $data = $request->all();
        $getIdUser = $data['idUser'];

        $fail = $this->checkAccess($getIdUser, $file_id);
        if ($fail) {
            return $fail;
        }

        $getFile = ObjectModel::where('object_id', '=', $file_id)
            ->first();

        $getParentFolder = ObjectModel::where('id', '=', $getFile->parent_id)
            ->first();

        $nameFolderFS = '';
        if ($getParentFolder) {
            $nameFolderFS = $getParentFolder->object_id.'/';
        }

        $extension = $getFile->extension;
        $pathFile = storage_path()."/app/public/".env('FOLDER_UPLOADS').$nameFolderFS.$file_id.'.'.$extension;

        return response()->download($pathFile);
    }
}
