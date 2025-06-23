<?php 
$stubs = [
    "searchEmails" => function($query) {
        if ($query === "is:unread") {
            error_log("E2E STUB: Matched searchEmails for is:unread");
            return [
                ["message_id" => "1977f1bc980404fc", "subject" => "Automatic payment scheduled for COMCAST CABLE COMMUNICATIONS as requested", "from" => "Bank of America <billpay@billpay.bankofamerica.com>"],
                ["message_id" => "1977e884033c279d", "subject" => "Re: Noise transmissions from 1207 Unit C to 1207 Unit A", "from" => "Alvaro Guerra <alvaro@tnwlc.com>"],
                ["message_id" => "1977dccf78acbbb1", "subject" => "Save Money Traveling Abroad, and more | June 2025", "from" => "\"Charles Schwab & Co., Inc.\" <donotreply@email.schwab.com>"],
                ["message_id" => "1977dc3c832deb25", "subject" => "macintosh se /30, Vintage Computing: 1 NEW!", "from" => "eBay <ebay@ebay.com>"],
                ["message_id" => "1977dc3c7b51b647", "subject" => "lego nintendo entertainment system, LEGO...: 4 NEW!", "from" => "eBay <ebay@ebay.com>"],
            ];
        }
        if (stripos($query, "from:alvaro") !== false || stripos($query, "from:\"alvaro guerra\"") !== false) {
            error_log("E2E STUB: Matched searchEmails for Alvaro");
            return [
                ["message_id" => "1977e884033c279d", "subject" => "Re: Noise transmissions from 1207 Unit C to 1207 Unit A", "from" => "Alvaro Guerra <alvaro@tnwlc.com>"]
            ];
        }
        if (stripos($query, "from:charles") !== false || stripos($query, "from:schwab") !== false) {
            error_log("E2E STUB: Matched searchEmails for Charles Schwab");
            return [
                ["message_id" => "1977dccf78acbbb1", "subject" => "Save Money Traveling Abroad, and more | June 2025", "from" => "\"Charles Schwab & Co., Inc.\" <donotreply@email.schwab.com>"]
            ];
        }
        error_log("E2E STUB ERROR: No stub for searchEmails with query: " . $query);
        return [];
    },
    "unread_emails" => function($maxResults) {
        error_log("E2E STUB: Matched unread_emails");
        // For now, return the same as a search, but limited.
        $all = [
            ["message_id" => "1977f1bc980404fc", "subject" => "Automatic payment scheduled for COMCAST CABLE COMMUNICATIONS as requested", "from" => "Bank of America <billpay@billpay.bankofamerica.com>"],
            ["message_id" => "1977e884033c279d", "subject" => "Re: Noise transmissions from 1207 Unit C to 1207 Unit A", "from" => "Alvaro Guerra <alvaro@tnwlc.com>"],
            ["message_id" => "1977dccf78acbbb1", "subject" => "Save Money Traveling Abroad, and more | June 2025", "from" => "\"Charles Schwab & Co., Inc.\" <donotreply@email.schwab.com>"],
            ["message_id" => "1977dc3c832deb25", "subject" => "macintosh se /30, Vintage Computing: 1 NEW!", "from" => "eBay <ebay@ebay.com>"],
            ["message_id" => "1977dc3c7b51b647", "subject" => "lego nintendo entertainment system, LEGO...: 4 NEW!", "from" => "eBay <ebay@ebay.com>"],
        ];
        return array_slice($all, 0, $maxResults);
    },
    "getEmail" => function($messageId) {
        if ($messageId === "1977f1bc980404fc") {
            error_log("E2E STUB: Matched getEmail for Bank of America email");
            return [
                "subject" => "Automatic payment scheduled for COMCAST CABLE COMMUNICATIONS as requested",
                "from" => "Bank of America <billpay@billpay.bankofamerica.com>",
                "to" => "xrmradio@gmail.com",
                "date" => "Tue, 17 Jun 2025 18:16:54 +0000 (UTC)",
                "body" => "New bill from COMCAST CABLE COMMUNICATIONS Account number: ************3186 AutoPay Amount: $ 123.00 Deliver by: 07/03/2025 Confirmation number: XHZ65W0MDZ"
            ];
        }
        if ($messageId === "1977dccf78acbbb1") {
            error_log("E2E STUB: Matched getEmail for Charles Schwab email");
            return [
                "subject" => "Save Money Traveling Abroad, and more | June 2025",
                "from" => "\"Charles Schwab & Co., Inc.\" <donotreply@email.schwab.com>",
                "to" => "<XRMRADIO@gmail.com>",
                "date" => "Tue, 17 Jun 2025 06:11:05 -0600",
                "body" => "Your monthly roundup of financial planning, retirement, and market trends from Schwab. 6 Ways to Save Money When Traveling Abroad. 5 Tips for Negotiating a Better Home Deal. How Much Car Can I Afford?"
            ];
        }
        if ($messageId === "1977e884033c279d") {
            error_log("E2E STUB: Matched getEmail for Alvaro Guerra email");
            return [
                "subject" => "Re: Noise transmissions from 1207 Unit C to 1207 Unit A",
                "from" => "Alvaro Guerra <alvaro@tnwlc.com>",
                "to" => "xrmradio@gmail.com",
                "date" => "Tue, 17 Jun 2025 15:23:12 -0700",
                "body" => "Hi, I wanted to follow up on the noise issue we discussed. The transmissions from Unit C have been particularly loud during evening hours. Please let me know if you need any additional information. Best regards, Alvaro"
            ];
        }
        error_log("E2E STUB ERROR: No stub for getEmail with messageId: " . $messageId);
        return null;
    },
    "markEmail" => function($messageId, $status) {
        error_log("E2E STUB: Received markEmail request for messageId: " . $messageId . " with status: " . $status);
        if ($messageId === "1977f1bc980404fc") {
            error_log("E2E STUB: Matched markEmail for Bank of America email with status: " . $status);
            return ["status" => "success", "message" => "The email has been marked as " . $status];
        }
        error_log("E2E STUB ERROR: No stub for markEmail with messageId: " . $messageId);
        return null;
    },
    "searchContacts" => function($name) {
        error_log("E2E STUB: Received searchContacts request for name: " . $name);
        if (stripos($name, 'randy') !== false || stripos($name, 'rand') !== false) {
            return ["status" => "found_contact", "contact" => ["email" => "randy.johnson@techcorp.com", "name" => "Randy Johnson"]];
        }
        if (stripos($name, 'john') !== false) {
            return ["status" => "found_multiple", "contacts" => [
                ["email" => "john.doe@techcorp.com", "name" => "John Doe"],
                ["email" => "john.smith@globalinc.com", "name" => "John Smith"]
            ]];
        }
        if (stripos($name, 'charles') !== false) {
            return ["status" => "found_contact", "contact" => ["email" => "customerservice@schwab.com", "name" => "Charles Schwab Customer Service"]];
        }
        if (stripos($name, 'zephyr') !== false) {
            return ["status" => "no_contacts_found", "message" => "I couldn't find anyone named 'Zephyr' in your contacts."];
        }
        return ["status" => "no_contacts_found", "message" => "I couldn't find anyone named '" . $name . "' in your contacts."];
    },
    "createDraft" => function($to, $subject, $body) {
        error_log("E2E STUB: Received createDraft request to: " . $to);
        return [
            "status" => "draft_created",
            "draft_id" => "draft_" . uniqid(),
            "to" => $to,
            "subject" => $subject
        ];
    },
    "sendEmail" => function($to, $subject, $body) {
        error_log("E2E STUB: Received sendEmail request to: " . $to);
        // In test mode with allow_email_sending false, this should create a draft
        return [
            "status" => "draft_created",
            "draft_id" => "draft_" . uniqid(),
            "to" => $to,
            "subject" => $subject,
            "message" => "Note: Sending is disabled, so a draft was created instead."
        ];
    },
    "createReplyDraft" => function($messageId, $body) {
        error_log("E2E STUB: Received createReplyDraft request for messageId: " . $messageId);
        // Map message IDs to senders for realistic replies
        $senderMap = [
            "1977e884033c279d" => ["email" => "alvaro@tnwlc.com", "subject" => "Re: Noise transmissions from 1207 Unit C to 1207 Unit A"],
            "1977f1bc980404fc" => ["email" => "billpay@billpay.bankofamerica.com", "subject" => "Re: Automatic payment scheduled for COMCAST CABLE COMMUNICATIONS as requested"],
            "1977dccf78acbbb1" => ["email" => "donotreply@email.schwab.com", "subject" => "Re: Save Money Traveling Abroad, and more | June 2025"]
        ];
        
        $details = $senderMap[$messageId] ?? ["email" => "original_sender@example.com", "subject" => "Re: Original Subject"];
        
        return [
            "status" => "draft_created",
            "draft_id" => "reply_draft_" . uniqid(),
            "to" => $details["email"],
            "subject" => $details["subject"]
        ];
    },
    "sendReply" => function($messageId, $body) {
        error_log("E2E STUB: Received sendReply request for messageId: " . $messageId);
        // Map message IDs to senders for realistic replies
        $senderMap = [
            "1977e884033c279d" => ["email" => "alvaro@tnwlc.com", "subject" => "Re: Noise transmissions from 1207 Unit C to 1207 Unit A"],
            "1977f1bc980404fc" => ["email" => "billpay@billpay.bankofamerica.com", "subject" => "Re: Automatic payment scheduled for COMCAST CABLE COMMUNICATIONS as requested"],
            "1977dccf78acbbb1" => ["email" => "donotreply@email.schwab.com", "subject" => "Re: Save Money Traveling Abroad, and more | June 2025"]
        ];
        
        $details = $senderMap[$messageId] ?? ["email" => "original_sender@example.com", "subject" => "Re: Original Subject"];
        
        // In test mode with allow_email_sending false, this should create a draft
        return [
            "status" => "draft_created",
            "draft_id" => "reply_draft_" . uniqid(),
            "to" => $details["email"],
            "subject" => $details["subject"],
            "message" => "Note: Sending is disabled, so a reply draft was created instead."
        ];
    },
    "sendDraft" => function($draftId) {
        error_log("E2E STUB: Received sendDraft request for draftId: " . $draftId);
        // Should be blocked in dev environment
        return [
            "status" => "blocked",
            "message" => "Email sending is disabled. The draft remains in your drafts folder."
        ];
    }
];
return $stubs;

