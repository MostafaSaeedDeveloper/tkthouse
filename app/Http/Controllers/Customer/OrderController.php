<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;use App\Models\Order;
class OrderController extends Controller {
 public function index(){ return view('customer.orders.index',['orders'=>auth()->user()->orders()->latest()->paginate(20)]); }
 public function show(Order $order){ abort_unless($order->user_id===auth()->id(),403); return view('customer.orders.show',compact('order')); }
}
