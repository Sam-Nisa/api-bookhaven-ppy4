<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Handle contact form submission
     */
    public function submitContactForm(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'fullName' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Log the contact form submission
            Log::info('Contact form submitted', [
                'name' => $data['fullName'],
                'email' => $data['email'],
                'subject' => $data['subject'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Here you can add email sending logic
            // For now, we'll just log it and return success
            
            // Example email sending (uncomment and configure when ready):
            /*
            Mail::send('emails.contact', $data, function($message) use ($data) {
                $message->to('admin@bookhaven.com')
                        ->subject('New Contact Form Submission: ' . $data['subject'])
                        ->replyTo($data['email'], $data['fullName']);
            });
            */

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your message! We will get back to you within 24 hours.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Contact form submission failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get contact information
     */
    public function getContactInfo()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'store_name' => 'BookHaven Cambodia',
                'address' => '123 Monivong Boulevard, Daun Penh, Phnom Penh 12206, Cambodia',
                'phone' => '+855 12 345 678',
                'email' => 'hello@bookhaven.com',
                'orders_email' => 'orders@bookhaven.com',
                'opening_hours' => [
                    'monday_friday' => '9:00 AM - 8:00 PM',
                    'saturday' => '10:00 AM - 6:00 PM',
                    'sunday' => 'Closed'
                ],
                'social_media' => [
                    'facebook' => 'https://facebook.com/bookhaven',
                    'twitter' => 'https://twitter.com/bookhaven',
                    'instagram' => 'https://instagram.com/bookhaven',
                    'linkedin' => 'https://linkedin.com/company/bookhaven'
                ]
            ]
        ], 200);
    }
}