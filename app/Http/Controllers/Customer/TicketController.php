<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;use App\Models\Ticket;use App\Services\TicketsPdfService;
class TicketController extends Controller {
 public function index(){ return view('customer.tickets.index',['tickets'=>Ticket::whereHas('order',fn($q)=>$q->where('user_id',auth()->id()))->paginate(20)]); }
 public function show(Ticket $ticket){ abort_unless($ticket->order->user_id===auth()->id(),403); return view('customer.tickets.show',compact('ticket')); }
 public function download(Ticket $ticket){ abort_unless($ticket->order->user_id===auth()->id(),403); $path=app(TicketsPdfService::class)->generate($ticket); return response()->download(storage_path('app/'.$path)); }
}
