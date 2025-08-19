# Telegram AI Photo Generator Bot

This is a single-file PHP-based Telegram bot that leverages the Fast-Creat API to generate AI-powered images, enhance photo quality, create logos, apply effects, convert images to anime style, and provide AI chat functionality. It includes user management, daily limits, point-based request system, and an admin panel for managing settings and broadcasting messages.

## Features
- **AI Image Generation**: Generate images from text prompts using the Fast-Creat API.
- **Photo Quality Enhancement**: Improve the quality of provided image URLs.
- **Logo Creation**: Create logos with customizable IDs and text (1–140).
- **Image Effects**: Apply effects to images with customizable IDs (1–80).
- **Anime Conversion**: Transform images into anime (Ghibli) style.
- **AI Chat**: Interact with AI using Fast-Creat's GPT-4 and chat endpoints.
- **User Management**: Tracks user activity, daily request limits, and points.
- **Admin Panel**: Allows admin to view stats, set daily limits, adjust request costs, award points, and broadcast messages (copy or forward).
- **Persian Interface**: Right-to-left text support with Persian messages.
- **Data Storage**: Uses JSON files for storing user data, settings, and states.
- **Rate Limiting**: Enforces daily request limits and point-based request costs.

## Requirements
- PHP 7.2+ with cURL and MBString extensions enabled.
- Telegram Bot Token (obtained from BotFather).
- Fast-Creat API keys for photo, quality, logo, GPT, chat, and Ghibli endpoints.
- Writeable `/data` and `/data/tmp` directories for storing JSON files and temporary images.

## Setup
1. Ensure PHP 7.2+ is installed with cURL and MBString extensions.
2. Obtain a Telegram Bot Token from BotFather and set it in `BOT_TOKEN`.
3. Set your Telegram user ID as `ADMIN_ID` for admin access.
4. Obtain Fast-Creat API keys and update the constants (`FAST_CREAT_*_APIKEY`).
5. Create a writeable `/data` directory and `/data/tmp` subdirectory in the same directory as the script.
6. Deploy the script to a web server and set up a Telegram webhook pointing to the script URL (e.g., `https://api.telegram.org/bot<TOKEN>/setWebhook?url=<SCRIPT_URL>`).
7. Run the bot by sending `/start` to the bot on Telegram.

## Usage
- **Start**: Send `/start` to see the main menu with inline keyboard options.
- **Generate Image**: Select "ساخت عکس با هوش مصنوعی" and send a text prompt.
- **Enhance Quality**: Select "افزایش کیفیت عکس" and send an image URL.
- **Create Logo**: Select "لوگوساز" or send `/logo`, then provide `id text` (e.g., `12 Fast Creat`).
- **Apply Effect**: Send `/effect`, then provide `id url` (e.g., `5 https://site/image.jpg`).
- **Anime Conversion**: Select "تبدیل به انیمه" or send `/anime`, then provide an image URL.
- **AI Chat**: Select "چت با هوش مصنوعی" or send `/chat`, then send a message for AI response.
- **Account Info**: Select "حساب من" to view points and daily request limits.
- **Admin Panel**: Send `/admin` (admin only) to access stats, set limits, adjust costs, award points, or broadcast messages.
- **Help**: Send `/help` or select "راهنما" for basic instructions.

## File Structure
- `index.php`: The main bot script handling all Telegram updates and API interactions.
- `/data/users.json`: Stores registered user IDs.
- `/data/users_db.json`: Stores detailed user records (points, requests, etc.).
- `/data/settings.json`: Stores bot settings (daily limit, request cost, initial points).
- `/data/state.json`: Tracks user states for conversation flow.
- `/data/tmp/`: Temporary storage for image files (data URI or base64).

## Code Structure
- **Configuration**: Defines constants for Telegram bot token, admin ID, and Fast-Creat API keys.
- **Storage**: JSON-based storage for users, settings, and states with helper functions (`loadJsonFile`, `saveJsonFile`).
- **User Management**: Tracks users, daily requests, and points (`registerUser`, `getUserRecord`, `chargeUserForRequest`).
- **Telegram API**: Handles Telegram API requests (`tgApi`, `sendMessage`, `sendPhotoUrl`, `sendPhotoFile`).
- **Fast-Creat API**: Integrates with Fast-Creat endpoints for image generation, quality enhancement, logo creation, effects, anime conversion, and AI chat.
- **Handlers**: Processes user commands and callback queries (`handleGenPhoto`, `handleAiChat`, etc.).
- **Text Normalization**: Cleans incoming and outgoing text for consistent processing (`normalizeIncomingText`, `normalizeOutgoingText`).
- **Image Handling**: Extracts and sends images from API responses (`extractImagesFromResponse`, `saveDataUriToFile`).

## Notes
- The bot enforces daily request limits and point costs, configurable via the admin panel.
- Images can be sent as URLs, data URIs, or base64, with a limit of 5 images per request.
- The admin panel is restricted to the `ADMIN_ID` and includes broadcasting capabilities.
- Temporary files in `/data/tmp` are automatically deleted after sending.
- Ensure proper file permissions for the `/data` directory to avoid write errors.
- The bot supports Persian text with right-to-left rendering and HTML parsing in Telegram.

## License
MIT License

---

# ربات تولید عکس هوش مصنوعی تلگرام

این یک ربات تلگرامی مبتنی بر PHP است که در یک فایل واحد پیاده‌سازی شده و از API Fast-Creat برای تولید تصاویر با هوش مصنوعی، بهبود کیفیت عکس، ساخت لوگو، اعمال افکت، تبدیل تصاویر به سبک انیمه و گفت‌وگو با هوش مصنوعی استفاده می‌کند. این ربات شامل مدیریت کاربران، محدودیت‌های روزانه، سیستم امتیازی برای درخواست‌ها و پنل ادمین برای مدیریت تنظیمات و ارسال پیام‌های همگانی است.

## ویژگی‌ها
- **تولید تصویر با هوش مصنوعی**: تولید تصاویر از متن‌های ورودی با استفاده از API Fast-Creat.
- **بهبود کیفیت عکس**: افزایش کیفیت تصاویر ارسالی از طریق URL.
- **ساخت لوگو**: ایجاد لوگو با شناسه و متن قابل تنظیم (1–140).
- **اعمال افکت**: افزودن افکت به تصاویر با شناسه‌های قابل تنظیم (1–80).
- **تبدیل به انیمه**: تبدیل تصاویر به سبک انیمه (غیبلی).
- **گفت‌وگو با هوش مصنوعی**: تعامل با هوش مصنوعی از طریق نقاط پایانی GPT-4 و چت.
- **مدیریت کاربران**: رصد فعالیت کاربران، محدودیت‌های روزانه و امتیازات.
- **پنل ادمین**: امکان مشاهده آمار، تنظیم محدودیت روزانه، تغییر هزینه درخواست‌ها، اعطای امتیاز و ارسال پیام‌های همگانی (کپی یا فوروارد).
- **رابط پارسی**: پشتیبانی از متن راست‌به‌چپ با پیام‌های پارسی.
- **ذخیره‌سازی داده**: استفاده از فایل‌های JSON برای ذخیره داده‌های کاربران، تنظیمات و حالت‌ها.
- **محدودیت نرخ**: اعمال محدودیت‌های روزانه و هزینه‌های امتیازی برای درخواست‌ها.

## پیش‌نیازها
- PHP نسخه 7.2 یا بالاتر با افزونه‌های cURL و MBString فعال.
- توکن ربات تلگرام (دریافت از BotFather).
- کلیدهای API Fast-Creat برای نقاط پایانی عکس، کیفیت، لوگو، GPT، چت و غیبلی.
- دایرکتوری‌های قابل نوشتن `/data` و `/data/tmp` برای ذخیره فایل‌های JSON و تصاویر موقت.

## راه‌اندازی
1. اطمینان حاصل کنید که PHP نسخه 7.2 یا بالاتر با افزونه‌های cURL و MBString نصب شده است.
2. توکن ربات تلگرام را از BotFather دریافت کرده و در `BOT_TOKEN` تنظیم کنید.
3. شناسه کاربری تلگرام خود را به عنوان `ADMIN_ID` برای دسترسی ادمین تنظیم کنید.
4. کلیدهای API Fast-Creat را دریافت کرده و ثابت‌های `FAST_CREAT_*_APIKEY` را به‌روزرسانی کنید.
5. دایرکتوری‌های `/data` و زیرپوشه `/data/tmp` را با قابلیت نوشتن در همان دایرکتوری اسکریپت ایجاد کنید.
6. اسکریپت را روی یک وب‌سرور مستقر کنید و وب‌هوک تلگرام را به URL اسکریپت تنظیم کنید (مثال: `https://api.telegram.org/bot<TOKEN>/setWebhook?url=<SCRIPT_URL>`).
7. با ارسال `/start` به ربات در تلگرام، آن را اجرا کنید.

## استفاده
- **شروع**: دستور `/start` را ارسال کنید تا منوی اصلی با کیبورد شیشه‌ای نمایش داده شود.
- **تولید تصویر**: گزینه "ساخت عکس با هوش مصنوعی" را انتخاب کرده و متن ورودی را ارسال کنید.
- **بهبود کیفیت**: گزینه "افزایش کیفیت عکس" را انتخاب کرده و URL تصویر را ارسال کنید.
- **ساخت لوگو**: گزینه "لوگوساز" یا دستور `/logo` را انتخاب کنید، سپس `id text` (مثال: `12 Fast Creat`) ارسال کنید.
- **اعمال افکت**: دستور `/effect` را ارسال کنید، سپس `id url` (مثال: `5 https://site/image.jpg`) ارسال کنید.
- **تبدیل به انیمه**: گزینه "تبدیل به انیمه" یا دستور `/anime` را انتخاب کرده و URL تصویر را ارسال کنید.
- **چت با هوش مصنوعی**: گزینه "چت با هوش مصنوعی" یا دستور `/chat` را انتخاب کرده و پیام خود را برای پاسخ هوش مصنوعی ارسال کنید.
- **اطلاعات حساب**: گزینه "حساب من" را انتخاب کنید تا امتیازات و محدودیت‌های روزانه را مشاهده کنید.
- **پنل ادمین**: دستور `/admin` (فقط برای ادمین) را ارسال کنید تا به آمار، تنظیم محدودیت‌ها، تغییر هزینه‌ها، اعطای امتیاز یا ارسال پیام‌های همگانی دسترسی پیدا کنید.
- **راهنما**: دستور `/help` یا گزینه "راهنما" را انتخاب کنید تا دستورالعمل‌های پایه را ببینید.

## ساختار فایل
- `index.php`: اسکریپت اصلی ربات که تمام به‌روزرسانی‌های تلگرام و تعاملات API را مدیریت می‌کند.
- `/data/users.json`: ذخیره شناسه‌های کاربران ثبت‌شده.
- `/data/users_db.json`: ذخیره سوابق دقیق کاربران (امتیازات، درخواست‌ها و غیره).
- `/data/settings.json`: ذخیره تنظیمات ربات (محدودیت روزانه، هزینه درخواست، امتیاز اولیه).
- `/data/state.json`: رصد حالت‌های کاربران برای جریان گفت‌وگو.
- `/data/tmp/` : ذخیره موقت فایل‌های تصویری (data URI یا base64).

## ساختار کد
- **پیکربندی**: تعریف ثابت‌ها برای توکن ربات تلگرام، شناسه ادمین و کلیدهای API Fast-Creat.
- **ذخیره‌سازی**: ذخیره‌سازی مبتنی بر JSON برای کاربران، تنظیمات و حالت‌ها با توابع کمکی (`loadJsonFile`, `saveJsonFile`).
- **مدیریت کاربران**: رصد کاربران، درخواست‌های روزانه و امتیازات (`registerUser`, `getUserRecord`, `chargeUserForRequest`).
- **API تلگرام**: مدیریت درخواست‌های API تلگرام (`tgApi`, `sendMessage`, `sendPhotoUrl`, `sendPhotoFile`).
- **API Fast-Creat**: ادغام با نقاط پایانی Fast-Creat برای تولید تصویر، بهبود کیفیت، ساخت لوگو، افکت‌ها، تبدیل به انیمه و چت با هوش مصنوعی.
- **مدیریت‌کننده‌ها**: پردازش دستورات کاربر و درخواست‌های callback (`handleGenPhoto`, `handleAiChat` و غیره).
- **نرمال‌سازی متن**: پاکسازی متن ورودی و خروجی برای پردازش یکنواخت (`normalizeIncomingText`, `normalizeOutgoingText`).
- **مدیریت تصاویر**: استخراج و ارسال تصاویر از پاسخ‌های API (`extractImagesFromResponse`, `saveDataUriToFile`).

## نکات
- ربات محدودیت‌های روزانه و هزینه‌های امتیازی را اعمال می‌کند که از طریق پنل ادمین قابل تنظیم است.
- تصاویر می‌توانند به صورت URL، data URI یا base64 ارسال شوند، با محدودیت 5 تصویر در هر درخواست.
- پنل ادمین به `ADMIN_ID` محدود شده و شامل قابلیت‌های ارسال همگانی است.
- فایل‌های موقت در `/data/tmp` پس از ارسال به‌طور خودکار حذف می‌شوند.
- اطمینان حاصل کنید که مجوزهای فایل برای دایرکتوری `/data` به درستی تنظیم شده تا از خطاهای نوشتن جلوگیری شود.
- ربات از متن پارسی با رندر راست‌به‌چپ و تجزیه HTML در تلگرام پشتیبانی می‌کند.

## مجوز
مجوز MIT

---

# Telegram AI照片生成机器人

这是一个基于PHP的单文件Telegram机器人，利用Fast-Creat API生成AI驱动的图像、提升照片质量、创建logo、应用效果、将图像转换为动漫风格并提供AI聊天功能。它包括用户管理、每日限制、基于积分的请求系统以及用于管理设置和广播消息的管理员面板。

## 功能
- **AI图像生成**：使用Fast-Creat API从文本提示生成图像。
- **照片质量提升**：提高提供的图像URL的质量。
- **Logo创建**：创建具有可自定义ID和文本的logo（1–140）。
- **图像效果**：为图像应用可自定义ID的效果（1–80）。
- **动漫转换**：将图像转换为动漫（吉卜力）风格。
- **AI聊天**：通过Fast-Creat的GPT-4和聊天端点与AI交互。
- **用户管理**：跟踪用户活动、每日请求限制和积分。
- **管理员面板**：允许管理员查看统计数据、设置每日限制、调整请求成本、分配积分和广播消息（复制或转发）。
- **波斯语界面**：支持从右到左的文本和波斯语消息。
- **数据存储**：使用JSON文件存储用户数据、设置和状态。
- **速率限制**：强制执行每日请求限制和基于积分的请求成本。

## 要求
- PHP 7.2+，启用cURL和MBString扩展。
- Telegram机器人令牌（从BotFather获取）。
- Fast-Creat API密钥，用于照片、质量、logo、GPT、聊天和吉卜力端点。
- 可写的`/data`和`/data/tmp`目录，用于存储JSON文件和临时图像。

## 设置
1. 确保安装了PHP 7.2+，并启用了cURL和MBString扩展。
2. 从BotFather获取Telegram机器人令牌并在`BOT_TOKEN`中设置。
3. 将您的Telegram用户ID设置为`ADMIN_ID`以获得管理员访问权限。
4. 获取Fast-Creat API密钥并更新常量（`FAST_CREAT_*_APIKEY`）。
5. 在与脚本相同的目录中创建可写的`/data`目录和`/data/tmp`子目录。
6. 将脚本部署到Web服务器，并设置指向脚本URL的Telegram webhook（例如：`https://api.telegram.org/bot<TOKEN>/setWebhook?url=<SCRIPT_URL>`）。
7. 通过在Telegram上向机器人发送`/start`来运行机器人。

## 使用
- **开始**：发送`/start`以查看带有内联键盘选项的主菜单。
- **生成图像**：选择“ساخت عکس با هوش مصنوعی”并发送文本提示。
- **提升质量**：选择“افزایش کیفیت عکس”并发送图像URL。
- **创建Logo**：选择“لوگوساز”或发送`/logo`，然后提供`id text`（例如：`12 Fast Creat`）。
- **应用效果**：发送`/effect`，然后提供`id url`（例如：`5 https://site/image.jpg`）。
- **动漫转换**：选择“تبدیل به انیمه”或发送`/anime`，然后提供图像URL。
- **AI聊天**：选择“چت با هوش مصنوعی”或发送`/chat`，然后发送消息以获取AI响应。
- **账户信息**：选择“حساب من”以查看积分和每日请求限制。
- **管理员面板**：发送`/admin`（仅限管理员）以访问统计数据、设置限制、调整成本、分配积分或广播消息。
- **帮助**：发送`/help`或选择“راهنما”以获取基本说明。

## 文件结构
- `index.php`：主机器人脚本，处理所有Telegram更新和API交互。
- `/data/users.json`：存储注册的用户ID。
- `/data/users_db.json`：存储详细的用户记录（积分、请求等）。
- `/data/settings.json`：存储机器人设置（每日限制、请求成本、初始积分）。
- `/data/state.json`：跟踪用户状态以实现对话流程。
- `/data/tmp/`：临时存储图像文件（data URI或base64）。

## 代码结构
- **配置**：定义Telegram机器人令牌、管理员ID和Fast-Creat API密钥的常量。
- **存储**：基于JSON的用户、设置和状态存储，带有辅助函数（`loadJsonFile`, `saveJsonFile`）。
- **用户管理**：跟踪用户、每日请求和积分（`registerUser`, `getUserRecord`, `chargeUserForRequest`）。
- **Telegram API**：处理Telegram API请求（`tgApi`, `sendMessage`, `sendPhotoUrl`, `sendPhotoFile`）。
- **Fast-Creat API**：与Fast-Creat端点集成，用于图像生成、质量提升、logo创建、效果、动漫转换和AI聊天。
- **处理程序**：处理用户命令和回调查询（`handleGenPhoto`, `handleAiChat`等）。
- **文本规范化**：清理输入和输出文本以实现一致处理（`normalizeIncomingText`, `normalizeOutgoingText`）。
- **图像处理**：从API响应中提取并发送图像（`extractImagesFromResponse`, `saveDataUriToFile`）。

## 注意事项
- 机器人强制执行每日请求限制和积分成本，可通过管理员面板配置。
- 图像可以作为URL、data URI或base64发送，每请求最多5张图像。
- 管理员面板仅限于`ADMIN_ID`，包括广播功能。
- `/data/tmp`中的临时文件在发送后会自动删除。
- 确保`/data`目录的文件权限正确设置以避免写入错误。
- 机器人支持波斯语文本，带有从右到左的渲染和Telegram中的HTML解析。

## 许可证
MIT许可证