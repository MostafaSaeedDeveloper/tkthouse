<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;use App\Models\Event;use App\Models\Order;use App\Models\Ticket;
class ReportController extends Controller {
 public function events(){
  $eventId=request('event_id');
  $orders=Order::where('status','paid')->when($eventId,fn($q)=>$q->whereHas('items.ticketType.event',fn($w)=>$w->whereKey($eventId)));
  $tickets=Ticket::when($eventId,fn($q)=>$q->where('event_id',$eventId));
  $data=['paid_orders'=>$orders->count(),'sales'=>$orders->sum('subtotal'),'fees'=>$orders->sum('fees_total'),'tickets_sold'=>$tickets->count(),'tickets_used'=>$tickets->whereNotNull('used_at')->count()];
  return view('admin.reports.events',['events'=>Event::all(),'data'=>$data]);
 }
}
