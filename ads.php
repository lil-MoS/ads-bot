<?php
$token = "##token##"; // 🔹 توکن ربات خود را اینجا وارد کنید
$api_url = "https://api.telegram.org/bot$token/";
$stats_file = "stats.txt"; // 🔹 فایل ذخیره اطلاعات کاربران
$channel_username = "@ERROR_APPS"; // 🔹 یوزرنیم کانال بدون فاصله و با @

// دریافت اطلاعات آپدیت
$update = json_decode(file_get_contents("php://input"), true);
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text = $update["message"]["text"] ?? null;
$callback_data = $update["callback_query"]["data"] ?? null;
$callback_chat_id = $update["callback_query"]["message"]["chat"]["id"] ?? null;
$user_id = $update["message"]["from"]["id"] ?? null;

// تابع ارسال پیام
function sendMessage($chat_id, $text, $keyboard = null) {
    global $api_url;
    $data = ["chat_id" => $chat_id, "text" => $text, "parse_mode" => "HTML"];
    if ($keyboard) {
        $data["reply_markup"] = json_encode($keyboard);
    }
    file_get_contents($api_url . "sendMessage?" . http_build_query($data));
}

// بررسی عضویت کاربر در کانال
function isUserJoined($user_id) {
    global $api_url, $channel_username;
    $response = json_decode(file_get_contents($api_url . "getChatMember?chat_id=$channel_username&user_id=$user_id"), true);
    $status = $response["result"]["status"] ?? "";
    return in_array($status, ["member", "administrator", "creator"]);
}

// تابع دریافت اطلاعات کاربر از فایل
function getUserStats($user_id) {
    global $stats_file;
    $users = file_exists($stats_file) ? file($stats_file, FILE_IGNORE_NEW_LINES) : [];
    foreach ($users as $user) {
        $data = explode("|", $user);
        if ($data[0] == $user_id) {
            return ["coins" => (int)$data[1], "referrals" => (int)$data[2]];
        }
    }
    return ["coins" => 3, "referrals" => 0]; // برای کاربران جدید ۳ سکه ثبت شود
}

// تابع ذخیره اطلاعات کاربر در فایل
function saveUserStats($user_id, $coins, $referrals) {
    global $stats_file;
    $users = file_exists($stats_file) ? file($stats_file, FILE_IGNORE_NEW_LINES) : [];
    $new_data = "$user_id|$coins|$referrals";
    $updated = false;
    
    foreach ($users as $index => $user) {
        if (explode("|", $user)[0] == $user_id) {
            $users[$index] = $new_data;
            $updated = true;
            break;
        }
    }
    if (!$updated) {
        $users[] = $new_data;
    }
    file_put_contents($stats_file, implode("\n", $users) . "\n");
}

// بررسی دستور /start
if ($text == "/start") {
    // بررسی عضویت در کانال
    if (!isUserJoined($user_id)) {
        sendMessage($chat_id, "❌ برای استفاده از ربات، ابتدا در کانال ما عضو شوید: $channel_username", [
            "inline_keyboard" => [
                [["text" => "📢 عضویت در کانال", "url" => "https://t.me/" . substr($channel_username, 1)]],
                [["text" => "✅ عضو شدم", "callback_data" => "joined"]], // دکمه جدید
            ]
        ]);
        exit;
    }

    $user_stats = getUserStats($user_id);
    if ($user_stats["coins"] == 3) { // ثبت فقط برای کاربران جدید
        saveUserStats($user_id, 3, 0);
    }

    // بررسی زیرمجموعه‌ها
    if (isset($update["message"]["text"]) && strpos($text, "/start ") !== false) {
        $referrer_id = explode(" ", $text)[1];
        if ($referrer_id != $user_id) {
            $referrer_stats = getUserStats($referrer_id);
            saveUserStats($referrer_id, $referrer_stats["coins"] + 2, $referrer_stats["referrals"] + 1);
            sendMessage($referrer_id, "🎉 یک نفر با لینک شما عضو شد! شما ۲ سکه هدیه گرفتید.");
        }
    }

    $keyboard = ["inline_keyboard" => [
        [["text" => "📢 ثبت تبلیغ", "callback_data" => "ad"]],
        [["text" => "👥 زیرمجموعه‌گیری", "callback_data" => "referral"]],
        [["text" => "💳 حساب من", "callback_data" => "account"]]
    ]];
    sendMessage($chat_id, "خوش اومدی ❤️‍🔥 \n
شما 3 سکه هدیه گرفتید که میتونید باهاش 3 بار تبلیغ خودتون رو بین تمام  گروه/کاربران به اشتراک بزارید.", $keyboard);
}

// دکمه‌های ربات
if ($callback_data) {
    if ($callback_data == "joined") {
        // بررسی عضویت کاربر در کانال پس از کلیک روی دکمه "عضو شدم"
        if (isUserJoined($callback_chat_id)) {
            $user_stats = getUserStats($callback_chat_id);
            $keyboard = [
                "inline_keyboard" => [
                    [["text" => "📢 ثبت تبلیغ", "callback_data" => "ad"]],
                    [["text" => "👥 زیرمجموعه‌گیری", "callback_data" => "referral"]],
                    [["text" => "💳 حساب من", "callback_data" => "account"]]
                ]
            ];
            sendMessage($callback_chat_id, "سلام! شما عضو کانال هستید. لطفاً یک گزینه را انتخاب کنید:", $keyboard);
        } else {
            sendMessage($callback_chat_id, "❌ هنوز عضو کانال نشده‌اید. برای استفاده از ربات، ابتدا در کانال عضو شوید.");
        }
        exit;
    }

    if (!isUserJoined($callback_chat_id)) {
        sendMessage($callback_chat_id, "❌ برای استفاده از ربات، ابتدا در کانال ما عضو شوید: $channel_username");
        exit;
    }

    $user_stats = getUserStats($callback_chat_id);

    if ($callback_data == "ad") {
        if ($user_stats["coins"] >= 1) {
            sendMessage($callback_chat_id, "📢 لطفاً تبلیغ خود را ارسال کنید.");
            saveUserStats($callback_chat_id, $user_stats["coins"] - 1, $user_stats["referrals"]);
            file_put_contents("ads_waiting.txt", $callback_chat_id . "\n", FILE_APPEND);
        } else {
            sendMessage($callback_chat_id, "❌ شما سکه کافی ندارید!");
        }
    } elseif ($callback_data == "referral") {
        sendMessage($callback_chat_id, "➖با ارسال لینک اختصاصی شما و دعوت دوستان خود به ربات 2 سکه هدیه دریافت میکنید .
\n
\n
\n👥 لینک دعوت شما: \n
https://t.me/Freeadsrobot?start=$callback_chat_id");
    } elseif ($callback_data == "account") {
        sendMessage($callback_chat_id, "💳 اطلاعات حساب شما:\n\n🔹 موجودی: " . $user_stats["coins"] . " سکه\n🔹 تعداد دعوت‌ها: " . $user_stats["referrals"]);
    }
}

// بررسی ارسال تبلیغ توسط کاربر
if ($chat_id && file_exists("ads_waiting.txt")) {
    $waiting_users = file("ads_waiting.txt", FILE_IGNORE_NEW_LINES);
    if (in_array($chat_id, $waiting_users)) {
        $users = file_exists($stats_file) ? file($stats_file, FILE_IGNORE_NEW_LINES) : [];
        foreach ($users as $user) {
            $user_id = explode("|", $user)[0];
            sendMessage($user_id, "📢 تبلیغ جدید:\n\n$text");
        }
        file_put_contents("ads_waiting.txt", str_replace($chat_id . "\n", "", file_get_contents("ads_waiting.txt")));
        sendMessage($chat_id, "✅ تبلیغ شما ارسال شد!");
    }
}
?>
