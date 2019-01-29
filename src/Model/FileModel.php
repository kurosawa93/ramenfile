<?php 
namespace Ordent\RamenFile\Model;
use Illuminate\Database\Eloquent\Model;

class FileModel extends Model{
    use FileModelTrait;
    protected $table = 'files';
    protected $fillable = [
        'files',
        'caption'
    ];

    protected $rules = [
        'files' => 'required|files'
    ];

    public function setFilesAttribute($value){
        $this->attributes['files'] = $this->uploadFile($value, 'files', null, '/files', 'public', null);
    }

    public function getFilesAttribute(){
        return $this->getFile($this->attributes['files']);
    }

}