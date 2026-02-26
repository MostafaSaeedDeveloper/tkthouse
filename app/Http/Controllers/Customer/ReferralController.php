<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;use App\Models\Referral;
class ReferralController extends Controller { public function index(){ return view('customer.referral.index',['referrals'=>Referral::where('referrer_user_id',auth()->id())->latest()->get()]); } }
