<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;use App\Models\Setting;
class SettingsController extends Controller {
 public function index(){ return view('admin.settings.index',['settings'=>Setting::all()->groupBy('group')]); }
 public function update(){ foreach(request('settings',[]) as $key=>$value){ Setting::updateOrCreate(['key'=>$key],['value'=>is_array($value)?json_encode($value):$value,'group'=>request('group','general')]); } return back(); }
}
