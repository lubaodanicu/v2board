<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketSave;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Utils\Helper;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function index (Request $request) {
        if ($request->input('id')) {
            $ticket = Ticket::where('id', $request->input('id'))
                ->where('user_id', $request->session()->get('id'))
                ->first();
            if (!$ticket) {
                abort(500, '工单不存在');
            }
            $ticket['message'] = TicketMessage::where('ticket_id', $ticket->id)->get();
            for ($i = 0; $i < count($ticket['message']); $i++) {
                if ($ticket['message'][$i]['user_id'] == $request->session()->get('id')) {
                    $ticket['message'][$i]['is_me'] = true;
                } else {
                    $ticket['message'][$i]['is_me'] = false;
                }
            }
            return response([
                'data' => $ticket
            ]);
        }
        return response([
            'data' => Ticket::where('user_id', $request->session()->get('id'))
                ->orderBy('created_at', 'DESC')
                ->get()
        ]);
    }

    public function save (TicketSave $request) {
        DB::beginTransaction();
        $ticket = Ticket::create(array_merge($request->only([
            'subject',
            'level'
        ]), [
            'user_id' => $request->session()->get('id')
        ]));
        if (!$ticket) {
            DB::rollback();
            abort(500, '工单创建失败');
        }
        $ticketMessage = TicketMessage::create([
            'user_id' => $request->session()->get('id'),
            'ticket_id' => $ticket->id,
            'message' => $request->input('message')
        ]);
        if (!$ticketMessage) {
            DB::rollback();
            abort(500, '工单创建失败');
        }
        DB::commit();
        return response([
            'data' => true
        ]);
    }

    public function reply (Request $request) {
        if (empty($request->input('id'))) {
            abort(500, '参数错误');
        }
        if (empty($request->input('message'))) {
            abort(500, '消息不能为空');
        }
        $ticket = Ticket::where('id', $request->input('id'))
            ->where('user_id', $request->session()->get('id'))
            ->first();
        if (!$ticket) {
            abort(500, '工单不存在');
        }
        if ($request->session()->get('id') == $this->getLastMessage()->user_id) {
            abort(500, '请等待技术支持回复');
        }
        $ticketMessage = TicketMessage::create([
            'user_id' => $request->session()->get('id'),
            'ticket_id' => $ticket->id,
            'message' => $request->input('message')
        ]);
        if (!$ticketMessage) {
            abort(500, '工单回复失败');
        }
        return response([
            'data' => true
        ]);
    }

    private function getLastMessage ($ticketId) {
        return TicketMessage::where('ticket_id', $ticketId)
            ->orderBy('id', 'DESC')
            ->first();
    }
}
