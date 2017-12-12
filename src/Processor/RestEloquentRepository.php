<?php
namespace Ordent\RamenRest\Processor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Ordent\RamenRest\Events\FileHandlerEvent;
class RestEloquentRepository
{
    protected $model = null;
    public function setModel(Model $model)
    {
        $this->model = $model;
    }
    public function getItem($id)
    {
        if (is_numeric($id)) {
            return $this->model->findOrFail($id);
        }
    }

    public function postItem($parameters)
    {
        $files = $this->getFilesParameter($parameters);
        $input = $this->getNonFilesParameter($parameters);
        $input = $this->resolveUpload($files, $input);
        return $this->model->create($input);
    }

    private function getFilesParameter($parameters)
    {
        $files = [];
        if (method_exists($this->model, "getFiles")) {
            $files = array_only($parameters, $this->model->getFiles());
        }
        return $files;
    }

    private function getNonFilesParameter($parameters)
    {
        $input = [];
        if (method_exists($this->model, "getFiles")) {
            $input = array_except($parameters, $this->model->getFiles());
        } else {
            $input = $parameters;
        }
        return $input;
    }
    
    public function putItem($id, $parameters)
    {
        $files = $this->getFilesParameter($parameters);
        $input = $this->getNonFilesParameter($parameters);
        $input = $this->resolveUpload($files, $input);
        $result = $this->model->findOrFail($id);
        
        $result->update($input);

        return $result;
    }

    public function deleteItem($id, $parameters)
    {
        if (array_key_exists("soft", $parameters)) {
            if ($parameters["soft"]) {
                $this->model->findOrFail($id)->delete();
            } else {
                $this->model->findOrFail($id)->forceDelete();
            }
        } else {
            $this->model->findOrFail($id)->forceDelete();
        }
        return [];
    }

    public function getCollection($attributes, $orderBy)
    {
        $model = $this->model;
        
        $model = $this->resolveWhere($model, $attributes);
        
        $model = $this->resolveOrderBy($model, $orderBy);

        return $model;
    }

    public function getDatatables($attributes){
        $model = $this->model;
        $model = $this->resolveDatatable($model, $attributes);

        return $model;
    }

    private function resolveDatatable($model, $attributes){
        
        if(array_key_exists('search', $attributes)){
            $search = $attributes['search'];
            $search = $search['value'];
            foreach($attributes['columns'] as $columns){
                if($columns['searchable']){
                    if(is_numeric($search)){
                        $model = $model->where($columns['name'], $search);
                    }else{
                        $model = $model->where($columns['name'], 'like', '%'.$search.'%');
                    }
                }
            }
        }

        if(array_key_exists('order', $attributes)){
            $columns = $attributes['columns'];
            $orders = $attributes['order'];
            
            foreach($orders as $order){
                $model = $model->orderBy($columns[$order[$column]], $order['dir']);
            }
        }

        return $model;
    }

    private function resolveWhere($model, $fields)
    {
        
        if (count($fields)>0) {
            
            foreach ($fields as $i => $l) {
                // more or less than
                if (substr($l, 0, 1) == ">" || substr($l, 0, 1) == "<") {
                    $model = $model->where($i, substr($l, 0, 1), substr($l, 1));
                // between range
                } elseif(substr($l, 0, 1) == "|"){
                    $out = explode(",", substr($l, 1));
                    $model = $model->whereBetween($i, $out);
                //get json path
                } elseif(substr($l, 0, 1) == "{"){
                    $out = explode("}", substr($l, 1));
                    $path = explode(",", $out[0]);
                    $key = "";
                    if(count($path) > 0){
                        $key = $i;
                        foreach ($path as $k => $p) {
                            $key = $key . "->" . $p; 
                        }
                    }else{
                        $key = $i."->".$path;
                    }
                    $model = $model->where($key, $out[1]);
                // not in
                } elseif (substr($l, 0, 1) == "!") {
                    $out = explode(",", substr($l, 1));
                    $model = $model->whereNotIn($i, $out);
                // like operator
                } elseif (substr($l, 0, 1) == "$") {
                    $model = $model->where($i, 'like', "%".substr($l, 1)."%");
                // get relation with path
                } elseif (substr($l, 0, 1) == ";"){
                    $path = explode(":", $l);
                    $rel = $path[1];
                    $value = $path[2];
                    $path = str_replace(";", "\\", $path[0]);
                    $temp = app($path)->find($value);
                    
                    if(!is_null($temp)){
                        $id = $temp->{$rel}->pluck("id")->all();
                        $model = $model->whereIn($i, $id);
                    }
                } elseif ($i == "scope"){
                    $path = explode(";", $l);
                    foreach ($path as $key => $value) {
                        $val = explode(":", $value);
                        try{
                            if(count($val)>1){
                                $arr = explode(",", $val[1]);
                                $model = $model->{$val[0]}($arr);
                            }else{
                                $model = $model->{$value}();
                            }
                        }catch(\BadMethodCallException $e){
                        
                        }
                    }
                // inside
                } else {
                    $in = explode(",", $l);
                    $model = $model->whereIn($i, $in);
                }
            }
        }
        
        return $model;
    }

    private function resolveOrderBy($model, $orderBy)
    {
        if (!is_null($orderBy)) {
            $orderBy = explode(",", $orderBy);
            foreach ($orderBy as $i => $o) {
                if (substr($o, 0, 1) == "<") {
                    $model = $model->orderBy(substr($o, 1), "desc");
                } else {
                    $model = $model->orderBy(substr($o, 1), "asc");
                }
            }
        }
        return $model;
    }

    private function resolveUpload($files, $input)
    {
        $string = [];
        // process all the file type input
        foreach ($files as $i => $file) {
            $string = [];
            if (is_array($file)) {
                // if the file that got sent are a form
                foreach ($file as $j => $item) {
                    if(!is_string($item)){
                        $temp = $item;
                        $item = event(new FileHandlerEvent($item, $i, $input));
                        if(is_array($item) && count($item) == 1){
                            $item = $item[0];
                        }else if(is_array($item) && count($item) == 0){
                            $item = $temp;
                        }
                    }
                    try{
                        if(is_string($item)){
                            array_push($string, $item);
                        }else{
                            array_push($string, asset('/storage/')."/".$item->store('images/'.$i, "public"));                        
                        }
                    }catch(FatalThrowableError $e){
                        abort(422, 'There\'s something wrong with the image you send. Please check property '.$i);
                    }
                }
            } else {
                if(!is_string($file)){
                    $temp = $file;
                    $file = event(new FileHandlerEvent($file, $i, $input));
                    if(is_array($file) && count($file) == 1){
                        $file = $file[0];
                    }else if(is_array($file) && count($file) == 0){
                        $file = $temp;
                    }
                }
                try{
                    if(is_string($file)){
                        array_push($string, $file);                        
                    }else{
                        array_push($string, asset('/storage/')."/".$file->store('images/'.$i, "public"));                    
                    }
                }catch(FatalThrowableError $e){
                    abort(422, 'There\'s something wrong with the image you send. Please check property '.$i);
                }
            }
            // check if theres any old images that need to be persist
            if(array_key_exists('_old_'.$i, $input)){
                $old = $input['_old_'.$i];
                if(!is_array($old)){
                    $old = [$old];
                }
                $string = array_merge($old, $string);
            }
            // insert images result into input
            if (count($string)>1) {
                $input[$i] = $string;
            } else {
                $input[$i] = $string[0];
            }
        }
        return $input;
    }
}
