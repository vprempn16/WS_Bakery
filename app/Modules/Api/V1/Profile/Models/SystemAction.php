<?php


namespace App\Modules\Api\V1\Profile\Models;


use Illuminate\Database\Eloquent\Model;


class SystemAction extends Model
{
protected $table = 'system_actions';
protected $fillable = ['action_key', 'label', 'security_check'];


public $timestamps = true;


public static function seedDefaults()
{
$defaults = [
['action_key' => 'view', 'label' => 'View Record', 'security_check' => 0],
['action_key' => 'create', 'label' => 'Create Record', 'security_check' => 0],
['action_key' => 'edit', 'label' => 'Edit Record', 'security_check' => 0],
['action_key' => 'delete', 'label' => 'Delete Record', 'security_check' => 0],
];


foreach ($defaults as $d) {
static::firstOrCreate(['action_key' => $d['action_key']], $d);
}
}
}
