<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
class WalletController extends Controller { public function index(){ return view('customer.wallet.index',['transactions'=>auth()->user()->walletTransactions()->latest()->paginate(20)]); } }
