<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function getContacts(Request $request)
    {
        $user = Auth::user();
        
        // Contacts are users who either sent a message to the auth user, or received a message from the auth user
        $contactIds = Message::where('sender_id', $user->id)->where('deleted_by_sender', false)
            ->pluck('receiver_id')
            ->merge(Message::where('receiver_id', $user->id)->where('deleted_by_receiver', false)->pluck('sender_id'))
            ->unique();
            
        // If a specific contact_id is provided via URL
        if ($request->has('contact_id')) {
            $contactIds->push($request->contact_id);
            $contactIds = $contactIds->unique();
        }
        
        $contacts = User::whereIn('id', $contactIds)->get();
        
        // Add unread count for each contact
        foreach ($contacts as $contact) {
            $contact->unread_count = Message::where('sender_id', $contact->id)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->where('deleted_by_receiver', false)
                ->count();
        }
        
        return response()->json($contacts);
    }

    public function getMessages($contactId)
    {
        $user = Auth::user();
        
        // Mark messages as read
        Message::where('sender_id', $contactId)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
            
            $messages = Message::where(function($q) use ($user, $contactId) {
                $q->where('sender_id', $user->id)
                  ->where('receiver_id', $contactId)
                  ->where('deleted_by_sender', false);
            })
            ->orWhere(function($q) use ($user, $contactId) {
                $q->where('sender_id', $contactId)
                  ->where('receiver_id', $user->id)
                  ->where('deleted_by_receiver', false);
            })
            ->orderBy('created_at', 'asc')
            ->get();
            
        return response()->json($messages);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);
        
        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
            'is_read' => false,
        ]);
        
        $receiver = User::find($request->receiver_id);
        if ($receiver) {
            $receiver->notify(new NewMessageNotification($message, Auth::user()));
        }

        // Broadcast the message via Pusher
        broadcast(new \App\Events\MessageSent($message))->toOthers();
        
        return response()->json($message, 201);
    }

    public function updateMessage(Request $request, Message $message)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        if ($message->sender_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->update([
            'message' => $request->message,
        ]);

        return response()->json(['message' => 'Message updated successfully', 'data' => $message]);
    }

    public function deleteMessage(Message $message)
    {
        if ($message->sender_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->delete();

        return response()->json(['message' => 'Message deleted successfully']);
    }

    public function deleteConversation($contactId)
    {
        $userId = Auth::id();
        
        $messages = Message::where(function ($query) use ($userId, $contactId) {
            $query->where('sender_id', $userId)
                  ->where('receiver_id', $contactId);
        })->orWhere(function ($query) use ($userId, $contactId) {
            $query->where('sender_id', $contactId)
                  ->where('receiver_id', $userId);
        })->get();

        foreach ($messages as $message) {
            if ($message->sender_id === $userId) {
                $message->deleted_by_sender = true;
            } else {
                $message->deleted_by_receiver = true;
            }
            $message->save();

            // If both parties deleted the conversation, hard delete the message
            if ($message->deleted_by_sender && $message->deleted_by_receiver) {
                $message->delete();
            }
        }

        return response()->json(['message' => 'Conversation deleted successfully']);
    }
}
