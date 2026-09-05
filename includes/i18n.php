<?php

declare(strict_types=1);

const WIDMS_LANGUAGES = ['en', 'si', 'ta'];

/*
 * Resolve language once per request and persist it so public pages and every
 * role dashboard use the same selection after navigation and authentication.
 */
function widmsLanguage(): string
{
    static $language = null;
    if ($language !== null) {
        return $language;
    }

    $requested = (string) ($_GET['lang'] ?? '');
    if (in_array($requested, WIDMS_LANGUAGES, true)) {
        $_SESSION['widms_language'] = $requested;
        setcookie('widms_language', $requested, [
            'expires' => time() + 31536000,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
        ]);
    }

    $stored = (string) ($_SESSION['widms_language'] ?? $_COOKIE['widms_language'] ?? 'en');
    $language = in_array($stored, WIDMS_LANGUAGES, true) ? $stored : 'en';
    $_SESSION['widms_language'] = $language;

    return $language;
}

function widmsTranslations(): array
{
    return [
        'si' => [
            'Disability Type Management'=>'ආබාධිතභාව වර්ග කළමනාකරණය','Create and maintain the disability types used by eligibility rules.'=>'සුදුසුකම් නීති සඳහා භාවිත කරන ආබාධිතභාව වර්ග සාදා කළමනාකරණය කරන්න.','Aid Item Rule Builder'=>'ආධාර අයිතම නීති සාදනය','Select a disability type and build an aid-item eligibility rule.'=>'ආබාධිතභාව වර්ගයක් තෝරා ආධාර අයිතම සුදුසුකම් නීතියක් සාදන්න.',
            'Manage Disability Types'=>'ආබාධිතභාව වර්ග කළමනාකරණය','Remove only disability types that are not used by an eligibility rule.'=>'සුදුසුකම් නීතියක භාවිත නොවන ආබාධිතභාව වර්ග පමණක් ඉවත් කරන්න.','No disability types are available.'=>'ආබාධිතභාව වර්ග නොමැත.','Remove this disability type permanently?'=>'මෙම ආබාධිතභාව වර්ගය ස්ථිරවම ඉවත් කරන්නද?','Disability type removed permanently.'=>'ආබාධිතභාව වර්ගය ස්ථිරවම ඉවත් කරන ලදී.',
            'Select prohibited items'=>'තහනම් කළ යුතු අයිතම තෝරන්න','Search prohibited items'=>'තහනම් අයිතම සොයන්න','Type to search items...'=>'අයිතම සෙවීමට ටයිප් කරන්න...','No matching items found.'=>'ගැළපෙන අයිතම හමු නොවීය.','Remove'=>'ඉවත් කරන්න',
            'Required fields'=>'අනිවාර්ය ක්ෂේත්‍ර','(Optional)'=>'(විකල්පයි)',
            'Language' => 'භාෂාව', 'English' => 'English', 'Sinhala' => 'සිංහල', 'Tamil' => 'தமிழ்',
            'Overview' => 'සමස්ත දසුන', 'Dashboard' => 'ප්‍රධාන පුවරුව', 'Pending Approvals' => 'අනුමැතිය අපේක්ෂිත',
            'User Management' => 'පරිශීලක කළමනාකරණය', 'Users' => 'පරිශීලකයින්', 'Divisions' => 'කොට්ඨාස',
            'Operations' => 'මෙහෙයුම්', 'Goods Requests' => 'භාණ්ඩ ඉල්ලීම්', 'Vision Camp Requests' => 'දෘෂ්ටි කඳවුරු ඉල්ලීම්',
            'Contact Lens Orders' => 'ස්පර්ශ කාච ඇණවුම්', 'Item Requests' => 'අයිතම ඉල්ලීම්', 'Beneficiaries' => 'ප්‍රතිලාභීන්',
            'Correction Requests' => 'නිවැරදි කිරීමේ ඉල්ලීම්', 'Procurement' => 'ප්‍රසම්පාදනය', 'Central Stock' => 'මධ්‍යම තොගය',
            'Suppliers' => 'සැපයුම්කරුවන්', 'Payments' => 'ගෙවීම්', 'Monitoring' => 'අධීක්ෂණය', 'Reports' => 'වාර්තා',
            'Audit Log' => 'විගණන සටහන', 'System' => 'පද්ධතිය', 'System Config' => 'පද්ධති සැකසුම්',
            'Request Goods from Store' => 'ගබඩාවෙන් භාණ්ඩ ඉල්ලන්න', 'Vision Camp / Direct Procurement' => 'දෘෂ්ටි කඳවුර / සෘජු ප්‍රසම්පාදනය',
            'Distribute Items' => 'අයිතම බෙදාහරින්න', 'Returns' => 'ආපසු භාරදීම්', 'Requests' => 'ඉල්ලීම්',
            'Aid Requests (Monitor)' => 'ආධාර ඉල්ලීම් (අධීක්ෂණය)', 'Correction Approval' => 'නිවැරදි කිරීමේ අනුමැතිය',
            'Config' => 'සැකසුම්', 'Eligibility Rules' => 'සුදුසුකම් නීති', 'Item Categories' => 'අයිතම කාණ්ඩ',
            'Social Service Officer Pools' => 'සමාජ සේවා නිලධාරී සංචිත', 'Inventory' => 'තොග කළමනාකරණය',
            'Receive Items' => 'අයිතම භාරගන්න', 'Current Stock' => 'වත්මන් තොගය', 'Dispatch' => 'නිකුත් කිරීම',
            'Approved Requests Ready for Dispatch' => 'නිකුත් කිරීමට සූදානම් අනුමත ඉල්ලීම්', 'Recently Dispatched' => 'මෑතකදී නිකුත් කළ',
            'My Work' => 'මගේ කාර්යය', 'My Pool Quota' => 'මගේ සංචිත කෝටාව', 'Distribute Aid' => 'ආධාර බෙදාහරින්න',
            'Pending Handover (Vision Camp)' => 'භාරදීමට නියමිත (දෘෂ්ටි කඳවුර)', 'Pending Lens Handover' => 'කාච භාරදීමට නියමිත',
            'My Aid Requests' => 'මගේ ආධාර ඉල්ලීම්', 'Process Return' => 'ආපසු භාරදීම සකසන්න', 'Request Status Report' => 'ඉල්ලීම් තත්ත්ව වාර්තාව',
            'Administrator' => 'පරිපාලක', 'Subject Officer' => 'විෂය නිලධාරී', 'Store Keeper' => 'ගබඩා භාරකරු',
            'Social Service Officer' => 'සමාජ සේවා නිලධාරී', 'Sign Out' => 'පිටවන්න',
            'Welcome back' => 'නැවත සාදරයෙන් පිළිගනිමු', 'Sign in to your account' => 'ඔබගේ ගිණුමට පිවිසෙන්න',
            'Enter your approved WIDMS credentials to continue.' => 'ඉදිරියට යාමට ඔබගේ අනුමත WIDMS පිවිසුම් තොරතුරු ඇතුළත් කරන්න.',
            'Username' => 'පරිශීලක නාමය', 'Password' => 'මුරපදය', 'Sign In' => 'පිවිසෙන්න',
            'New to WIDMS?' => 'WIDMS සඳහා අලුත්ද?', 'Request an account' => 'ගිණුමක් ඉල්ලන්න',
            'SSO Division Assignments'=>'SSO කොට්ඨාස පැවරුම්','Approved SSO by DS Division'=>'DS කොට්ඨාසය අනුව අනුමත SSO','Service Division'=>'සේවා කොට්ඨාසය','Unavailable: active SSO assigned'=>'ලබාගත නොහැක: සක්‍රිය SSO නිලධාරියෙකු පවරා ඇත','This division already has an active Social Service Officer.'=>'මෙම කොට්ඨාසයට දැනටමත් සක්‍රිය සමාජ සේවා නිලධාරියෙකු පවරා ඇත.','Delete this eligibility rule permanently?'=>'මෙම සුදුසුකම් නීතිය ස්ථිරවම මකා දමන්නද?',
            'Review active and deactivated Social Service Officer assignments.'=>'සක්‍රිය සහ අක්‍රිය කළ සමාජ සේවා නිලධාරී පැවරුම් සමාලෝචනය කරන්න.',
            'District'=>'දිස්ත්‍රික්කය','DS Division'=>'ප්‍රාදේශීය ලේකම් කොට්ඨාසය','Approved SSO'=>'අනුමත SSO','Contact'=>'සම්බන්ධතා','Status'=>'තත්ත්වය','Reason'=>'හේතුව','Action'=>'ක්‍රියාව',
            'Active'=>'සක්‍රිය','Deactivated'=>'අක්‍රිය කළ','No approved SSO'=>'අනුමත SSO නොමැත','No DS Divisions available.'=>'DS කොට්ඨාස නොමැත.',
            'Deactivate'=>'අක්‍රිය කරන්න','Reactivate'=>'නැවත සක්‍රිය කරන්න','Reason required for deactivation'=>'අක්‍රිය කිරීමට හේතුව අවශ්‍යයි','Reason required for rejection'=>'ප්‍රතික්ෂේප කිරීමට හේතුව අවශ්‍යයි',
            'A deactivation reason is required.'=>'අක්‍රිය කිරීමට හේතුවක් අවශ්‍යයි.','A rejection reason is required.'=>'ප්‍රතික්ෂේප කිරීමට හේතුවක් අවශ්‍යයි.',
            'Social Service Officer deactivated successfully.'=>'සමාජ සේවා නිලධාරියා සාර්ථකව අක්‍රිය කරන ලදී.','Social Service Officer reactivated successfully.'=>'සමාජ සේවා නිලධාරියා නැවත සක්‍රිය කරන ලදී.',
            'Rejection reason'=>'ප්‍රතික්ෂේප කිරීමේ හේතුව','Deactivation reason'=>'අක්‍රිය කිරීමේ හේතුව','Enter a clear reason before rejecting'=>'ප්‍රතික්ෂේප කිරීමට පෙර පැහැදිලි හේතුවක් ඇතුළත් කරන්න','Enter a clear reason before deactivating'=>'අක්‍රිය කිරීමට පෙර පැහැදිලි හේතුවක් ඇතුළත් කරන්න','Approve'=>'අනුමත කරන්න','Reject'=>'ප්‍රතික්ෂේප කරන්න',
            'Reject registration request'=>'ලියාපදිංචි ඉල්ලීම ප්‍රතික්ෂේප කරන්න','Confirm rejection'=>'ප්‍රතික්ෂේප කිරීම තහවුරු කරන්න','Deactivate Social Service Officer'=>'සමාජ සේවා නිලධාරියා අක්‍රිය කරන්න','Confirm deactivation'=>'අක්‍රිය කිරීම තහවුරු කරන්න',
        ],
        'ta' => [
            'Disability Type Management'=>'மாற்றுத்திறன் வகை மேலாண்மை','Create and maintain the disability types used by eligibility rules.'=>'தகுதி விதிகளில் பயன்படுத்தப்படும் மாற்றுத்திறன் வகைகளை உருவாக்கி நிர்வகிக்கவும்.','Aid Item Rule Builder'=>'உதவிப் பொருள் விதி உருவாக்கி','Select a disability type and build an aid-item eligibility rule.'=>'மாற்றுத்திறன் வகையைத் தேர்ந்தெடுத்து உதவிப் பொருள் தகுதி விதியை உருவாக்கவும்.',
            'Manage Disability Types'=>'மாற்றுத்திறன் வகைகளை நிர்வகிக்கவும்','Remove only disability types that are not used by an eligibility rule.'=>'தகுதி விதியில் பயன்படுத்தப்படாத மாற்றுத்திறன் வகைகளை மட்டும் அகற்றவும்.','No disability types are available.'=>'மாற்றுத்திறன் வகைகள் எதுவும் கிடைக்கவில்லை.','Remove this disability type permanently?'=>'இந்த மாற்றுத்திறன் வகையை நிரந்தரமாக அகற்றவா?','Disability type removed permanently.'=>'மாற்றுத்திறன் வகை நிரந்தரமாக அகற்றப்பட்டது.',
            'Select prohibited items'=>'தடைசெய்ய வேண்டிய பொருட்களைத் தேர்ந்தெடுக்கவும்','Search prohibited items'=>'தடைசெய்யப்பட்ட பொருட்களைத் தேடவும்','Type to search items...'=>'பொருட்களைத் தேட தட்டச்சு செய்யவும்...','No matching items found.'=>'பொருந்தும் பொருட்கள் எதுவும் கிடைக்கவில்லை.','Remove'=>'அகற்று',
            'Required fields'=>'கட்டாயப் புலங்கள்','(Optional)'=>'(விருப்பம்)',
            'Language' => 'மொழி', 'English' => 'English', 'Sinhala' => 'සිංහල', 'Tamil' => 'தமிழ்',
            'Overview' => 'கண்ணோட்டம்', 'Dashboard' => 'முகப்புப் பலகை', 'Pending Approvals' => 'நிலுவை ஒப்புதல்கள்',
            'User Management' => 'பயனர் மேலாண்மை', 'Users' => 'பயனர்கள்', 'Divisions' => 'பிரிவுகள்',
            'Operations' => 'செயற்பாடுகள்', 'Goods Requests' => 'பொருள் கோரிக்கைகள்', 'Vision Camp Requests' => 'பார்வை முகாம் கோரிக்கைகள்',
            'Contact Lens Orders' => 'தொடர்பு வில்லை ஆணைகள்', 'Item Requests' => 'பொருள் கோரிக்கைகள்', 'Beneficiaries' => 'பயனாளிகள்',
            'Correction Requests' => 'திருத்தக் கோரிக்கைகள்', 'Procurement' => 'கொள்முதல்', 'Central Stock' => 'மத்திய கையிருப்பு',
            'Suppliers' => 'வழங்குநர்கள்', 'Payments' => 'கொடுப்பனவுகள்', 'Monitoring' => 'கண்காணிப்பு', 'Reports' => 'அறிக்கைகள்',
            'Audit Log' => 'தணிக்கைப் பதிவு', 'System' => 'அமைப்பு', 'System Config' => 'அமைப்பு உள்ளமைவு',
            'Request Goods from Store' => 'களஞ்சியத்திலிருந்து பொருட்களை கோருக', 'Vision Camp / Direct Procurement' => 'பார்வை முகாம் / நேரடி கொள்முதல்',
            'Distribute Items' => 'பொருட்களை விநியோகிக்க', 'Returns' => 'திருப்பல்கள்', 'Requests' => 'கோரிக்கைகள்',
            'Aid Requests (Monitor)' => 'உதவிக் கோரிக்கைகள் (கண்காணிப்பு)', 'Correction Approval' => 'திருத்த ஒப்புதல்',
            'Config' => 'அமைப்புகள்', 'Eligibility Rules' => 'தகுதி விதிகள்', 'Item Categories' => 'பொருள் வகைகள்',
            'Social Service Officer Pools' => 'சமூக சேவை அலுவலர் கையிருப்புகள்', 'Inventory' => 'சரக்கு',
            'Receive Items' => 'பொருட்களைப் பெறுக', 'Current Stock' => 'தற்போதைய கையிருப்பு', 'Dispatch' => 'அனுப்புதல்',
            'Approved Requests Ready for Dispatch' => 'அனுப்பத் தயாரான அங்கீகரிக்கப்பட்ட கோரிக்கைகள்', 'Recently Dispatched' => 'அண்மையில் அனுப்பப்பட்டவை',
            'My Work' => 'எனது பணி', 'My Pool Quota' => 'எனது கையிருப்பு ஒதுக்கீடு', 'Distribute Aid' => 'உதவியை விநியோகிக்க',
            'Pending Handover (Vision Camp)' => 'நிலுவை ஒப்படைப்பு (பார்வை முகாம்)', 'Pending Lens Handover' => 'நிலுவை வில்லை ஒப்படைப்பு',
            'My Aid Requests' => 'எனது உதவிக் கோரிக்கைகள்', 'Process Return' => 'திருப்பலைச் செயல்படுத்துக', 'Request Status Report' => 'கோரிக்கை நிலை அறிக்கை',
            'Administrator' => 'நிர்வாகி', 'Subject Officer' => 'விடய அலுவலர்', 'Store Keeper' => 'களஞ்சியப் பொறுப்பாளர்',
            'Social Service Officer' => 'சமூக சேவை அலுவலர்', 'Sign Out' => 'வெளியேறு',
            'Welcome back' => 'மீண்டும் வரவேற்கிறோம்', 'Sign in to your account' => 'உங்கள் கணக்கில் உள்நுழைக',
            'Enter your approved WIDMS credentials to continue.' => 'தொடர உங்கள் அங்கீகரிக்கப்பட்ட WIDMS உள்நுழைவு விவரங்களை உள்ளிடுக.',
            'Username' => 'பயனர் பெயர்', 'Password' => 'கடவுச்சொல்', 'Sign In' => 'உள்நுழைக',
            'New to WIDMS?' => 'WIDMS-க்கு புதியவரா?', 'Request an account' => 'கணக்கைக் கோருக',
            'SSO Division Assignments'=>'SSO பிரிவு நியமனங்கள்','Approved SSO by DS Division'=>'DS பிரிவின்படி அங்கீகரிக்கப்பட்ட SSO','Service Division'=>'சேவைப் பிரிவு','Unavailable: active SSO assigned'=>'கிடைக்காது: செயலில் உள்ள SSO நியமிக்கப்பட்டுள்ளார்','This division already has an active Social Service Officer.'=>'இந்தப் பிரிவுக்கு ஏற்கனவே செயலில் உள்ள சமூக சேவை அலுவலர் நியமிக்கப்பட்டுள்ளார்.','Delete this eligibility rule permanently?'=>'இந்தத் தகுதி விதியை நிரந்தரமாக நீக்கவா?',
            'Review active and deactivated Social Service Officer assignments.'=>'செயலில் உள்ள மற்றும் செயலிழக்கப்பட்ட சமூக சேவை அலுவலர் நியமனங்களை மதிப்பாய்வு செய்க.',
            'District'=>'மாவட்டம்','DS Division'=>'பிரதேச செயலகப் பிரிவு','Approved SSO'=>'அங்கீகரிக்கப்பட்ட SSO','Contact'=>'தொடர்பு','Status'=>'நிலை','Reason'=>'காரணம்','Action'=>'செயல்',
            'Active'=>'செயலில்','Deactivated'=>'செயலிழக்கப்பட்டது','No approved SSO'=>'அங்கீகரிக்கப்பட்ட SSO இல்லை','No DS Divisions available.'=>'DS பிரிவுகள் இல்லை.',
            'Deactivate'=>'செயலிழக்கச் செய்','Reactivate'=>'மீண்டும் செயல்படுத்து','Reason required for deactivation'=>'செயலிழக்கச் செய்வதற்கான காரணம் தேவை','Reason required for rejection'=>'நிராகரிப்பதற்கான காரணம் தேவை',
            'A deactivation reason is required.'=>'செயலிழக்கச் செய்வதற்கான காரணம் அவசியம்.','A rejection reason is required.'=>'நிராகரிப்பதற்கான காரணம் அவசியம்.',
            'Social Service Officer deactivated successfully.'=>'சமூக சேவை அலுவலர் வெற்றிகரமாக செயலிழக்கச் செய்யப்பட்டார்.','Social Service Officer reactivated successfully.'=>'சமூக சேவை அலுவலர் மீண்டும் செயல்படுத்தப்பட்டார்.',
            'Rejection reason'=>'நிராகரிப்பதற்கான காரணம்','Deactivation reason'=>'செயலிழக்கச் செய்வதற்கான காரணம்','Enter a clear reason before rejecting'=>'நிராகரிப்பதற்கு முன் தெளிவான காரணத்தை உள்ளிடுக','Enter a clear reason before deactivating'=>'செயலிழக்கச் செய்வதற்கு முன் தெளிவான காரணத்தை உள்ளிடுக','Approve'=>'அங்கீகரி','Reject'=>'நிராகரி',
            'Reject registration request'=>'பதிவுக் கோரிக்கையை நிராகரி','Confirm rejection'=>'நிராகரிப்பை உறுதிப்படுத்து','Deactivate Social Service Officer'=>'சமூக சேவை அலுவலரை செயலிழக்கச் செய்','Confirm deactivation'=>'செயலிழப்பை உறுதிப்படுத்து',
        ],
    ];
}

/* English source text is the fallback, which prevents blank UI when a new key is added. */
function t(string $english): string
{
    $language = widmsLanguage();
    if ($language === 'en') {
        return $english;
    }

    // Shared action text keeps newly linked dashboard cards trilingual.
    $sharedActions = [
        'si' => ['View details' => 'විස්තර බලන්න', 'New Aid Request' => 'නව ආධාර ඉල්ලීම', "Please select NIC or Elders' Identity Card."=>'කරුණාකර හැඳුනුම්පත හෝ වැඩිහිටි හැඳුනුම්පත තෝරන්න.','Enter a valid Sri Lankan NIC.'=>'වලංගු ශ්‍රී ලාංකික හැඳුනුම්පත් අංකයක් ඇතුළත් කරන්න.',"Enter a valid Elders' Identity Card number."=>'වලංගු වැඩිහිටි හැඳුනුම්පත් අංකයක් ඇතුළත් කරන්න.'],
        'ta' => ['View details' => 'விவரங்களைக் காண்க', 'New Aid Request' => 'புதிய உதவிக் கோரிக்கை', "Please select NIC or Elders' Identity Card."=>'அடையாள அட்டை அல்லது முதியோர் அடையாள அட்டையைத் தேர்ந்தெடுக்கவும்.','Enter a valid Sri Lankan NIC.'=>'செல்லுபடியாகும் இலங்கை அடையாள அட்டை எண்ணை உள்ளிடவும்.',"Enter a valid Elders' Identity Card number."=>'செல்லுபடியாகும் முதியோர் அடையாள அட்டை எண்ணை உள்ளிடவும்.'],
    ];

    // Aid Request workflow copy is grouped here so its long form and table stay consistent.
    $aidRequest = [
        'si' => [
            'Location Details'=>'ස්ථාන විස්තර','District'=>'දිස්ත්‍රික්කය','Select District'=>'දිස්ත්‍රික්කය තෝරන්න','D.S. Division'=>'ප්‍රාදේශීය ලේකම් කොට්ඨාසය','Select DS Division'=>'ප්‍රාදේශීය ලේකම් කොට්ඨාසය තෝරන්න','G.N. Division'=>'ග්‍රාම නිලධාරී කොට්ඨාසය','Select GN Division'=>'ග්‍රාම නිලධාරී කොට්ඨාසය තෝරන්න',
            'Beneficiary Details'=>'ප්‍රතිලාභියාගේ විස්තර','Full Name'=>'සම්පූර්ණ නම','As per NIC / Birth Certificate'=>'හැඳුනුම්පත / උප්පැන්න සහතිකය අනුව','Identification'=>'හඳුනාගැනීම','Elders\' Identity Card'=>'වැඩිහිටි හැඳුනුම්පත','NIC Number'=>'හැඳුනුම්පත් අංකය','Elders\' Identity Card Number'=>'වැඩිහිටි හැඳුනුම්පත් අංකය','Date of Birth'=>'උපන් දිනය','Gender'=>'ස්ත්‍රී පුරුෂ භාවය','Select'=>'තෝරන්න','Male'=>'පිරිමි','Female'=>'ගැහැණු','Other'=>'වෙනත්','Phone Number'=>'දුරකථන අංකය','Address'=>'ලිපිනය',
            'Disability & Aid Requested'=>'ආබාධිත තත්ත්වය සහ ඉල්ලා ඇති ආධාරය','Nature of Disability'=>'ආබාධිත තත්ත්වයේ ස්වභාවය','Select disability'=>'ආබාධිත තත්ත්වය තෝරන්න','Managed by Admin in System Configuration'=>'පද්ධති සැකසුම් තුළ පරිපාලක විසින් කළමනාකරණය කරයි','Aid Requested'=>'ඉල්ලා ඇති ආධාරය','Select Aid Type'=>'ආධාර වර්ගය තෝරන්න','Quantity'=>'ප්‍රමාණය','Prescription Power'=>'වෛද්‍ය නිර්දේශිත බලය','Additional Notes'=>'අමතර සටහන්',
            'Official Approvals'=>'නිල අනුමැතීන්','Check each official who has approved this application.'=>'මෙම අයදුම්පත අනුමත කළ සෑම නිලධාරියෙකුම සලකුණු කරන්න.','Government Medical Officer'=>'රජයේ වෛද්‍ය නිලධාරී','Grama Niladhari'=>'ග්‍රාම නිලධාරී','Social Services Officer'=>'සමාජ සේවා නිලධාරී','Divisional Secretary'=>'ප්‍රාදේශීය ලේකම්','Submit Aid Request'=>'ආධාර ඉල්ලීම ඉදිරිපත් කරන්න','Save as Draft'=>'කෙටුම්පතක් ලෙස සුරකින්න',
            'My Submitted Requests'=>'මා ඉදිරිපත් කළ ඉල්ලීම්','Search name or NIC...'=>'නම හෝ හැඳුනුම්පත සොයන්න...','All Status'=>'සියලු තත්ත්ව','Draft'=>'කෙටුම්පත','Pending'=>'පොරොත්තුවෙන්','Approved'=>'අනුමත','Rejected'=>'ප්‍රතික්ෂේපිත','Goods Requested'=>'භාණ්ඩ ඉල්ලා ඇත','Distributed'=>'බෙදා දී ඇත','ID'=>'අංකය','Beneficiary'=>'ප්‍රතිලාභියා','Age'=>'වයස','Approvals'=>'අනුමැතීන්','Submitted'=>'ඉදිරිපත් කළ දිනය','Notes'=>'සටහන්','No requests yet.'=>'තවම ඉල්ලීම් නොමැත.'
        ],
        'ta' => [
            'Location Details'=>'இருப்பிட விவரங்கள்','District'=>'மாவட்டம்','Select District'=>'மாவட்டத்தைத் தேர்ந்தெடுக்கவும்','D.S. Division'=>'பிரதேச செயலகப் பிரிவு','Select DS Division'=>'பிரதேச செயலகப் பிரிவைத் தேர்ந்தெடுக்கவும்','G.N. Division'=>'கிராம அலுவலர் பிரிவு','Select GN Division'=>'கிராம அலுவலர் பிரிவைத் தேர்ந்தெடுக்கவும்',
            'Beneficiary Details'=>'பயனாளி விவரங்கள்','Full Name'=>'முழுப் பெயர்','As per NIC / Birth Certificate'=>'அடையாள அட்டை / பிறப்புச் சான்றிதழின்படி','Identification'=>'அடையாளம்','Elders\' Identity Card'=>'முதியோர் அடையாள அட்டை','NIC Number'=>'அடையாள அட்டை எண்','Elders\' Identity Card Number'=>'முதியோர் அடையாள அட்டை எண்','Date of Birth'=>'பிறந்த தேதி','Gender'=>'பாலினம்','Select'=>'தேர்ந்தெடுக்கவும்','Male'=>'ஆண்','Female'=>'பெண்','Other'=>'மற்றவை','Phone Number'=>'தொலைபேசி எண்','Address'=>'முகவரி',
            'Disability & Aid Requested'=>'மாற்றுத்திறன் மற்றும் கோரப்பட்ட உதவி','Nature of Disability'=>'மாற்றுத்திறனின் தன்மை','Select disability'=>'மாற்றுத்திறனைத் தேர்ந்தெடுக்கவும்','Managed by Admin in System Configuration'=>'கணினி அமைப்பில் நிர்வாகியால் நிர்வகிக்கப்படுகிறது','Aid Requested'=>'கோரப்பட்ட உதவி','Select Aid Type'=>'உதவி வகையைத் தேர்ந்தெடுக்கவும்','Quantity'=>'அளவு','Prescription Power'=>'மருத்துவப் பரிந்துரை வலிமை','Additional Notes'=>'கூடுதல் குறிப்புகள்',
            'Official Approvals'=>'அதிகாரப்பூர்வ ஒப்புதல்கள்','Check each official who has approved this application.'=>'இந்த விண்ணப்பத்தை ஒப்புதல் அளித்த ஒவ்வொரு அதிகாரியையும் குறிக்கவும்.','Government Medical Officer'=>'அரச மருத்துவ அதிகாரி','Grama Niladhari'=>'கிராம அலுவலர்','Social Services Officer'=>'சமூக சேவை அதிகாரி','Divisional Secretary'=>'பிரதேச செயலாளர்','Submit Aid Request'=>'உதவிக் கோரிக்கையைச் சமர்ப்பிக்கவும்','Save as Draft'=>'வரைவாகச் சேமிக்கவும்',
            'My Submitted Requests'=>'நான் சமர்ப்பித்த கோரிக்கைகள்','Search name or NIC...'=>'பெயர் அல்லது அடையாள எண்ணைத் தேடவும்...','All Status'=>'அனைத்து நிலைகளும்','Draft'=>'வரைவு','Pending'=>'நிலுவையில்','Approved'=>'அங்கீகரிக்கப்பட்டது','Rejected'=>'நிராகரிக்கப்பட்டது','Goods Requested'=>'பொருட்கள் கோரப்பட்டன','Distributed'=>'விநியோகிக்கப்பட்டது','ID'=>'எண்','Beneficiary'=>'பயனாளி','Age'=>'வயது','Approvals'=>'ஒப்புதல்கள்','Submitted'=>'சமர்ப்பித்த தேதி','Notes'=>'குறிப்புகள்','No requests yet.'=>'இதுவரை கோரிக்கைகள் இல்லை.'
        ],
    ];

    // Placeholders and small guidance text are translated separately for readability.
    $aidRequestGuidance = [
        'si' => [
            'Select at least one identification method.'=>'අවම වශයෙන් එක් හඳුනාගැනීමේ ක්‍රමයක් තෝරන්න.','e.g. 901234567V or 199012345678'=>'උදා: 901234567V හෝ 199012345678',"Enter Elders' Identity Card number"=>'වැඩිහිටි හැඳුනුම්පත් අංකය ඇතුළත් කරන්න','e.g. 077-1234567'=>'උදා: 077-1234567','Full residential address...'=>'සම්පූර්ණ පදිංචි ලිපිනය...','e.g. -2.00 or +1.50'=>'උදා: -2.00 හෝ +1.50','Any additional information...'=>'අමතර තොරතුරු ඇත්නම්...','Power:'=>'බලය:',
        ],
        'ta' => [
            'Select at least one identification method.'=>'குறைந்தது ஓர் அடையாள முறையைத் தேர்ந்தெடுக்கவும்.','e.g. 901234567V or 199012345678'=>'உதா: 901234567V அல்லது 199012345678',"Enter Elders' Identity Card number"=>'முதியோர் அடையாள அட்டை எண்ணை உள்ளிடவும்','e.g. 077-1234567'=>'உதா: 077-1234567','Full residential address...'=>'முழுமையான வசிப்பிட முகவரி...','e.g. -2.00 or +1.50'=>'உதா: -2.00 அல்லது +1.50','Any additional information...'=>'கூடுதல் தகவல்கள் ஏதேனும் இருந்தால்...','Power:'=>'வலிமை:',
        ],
    ];

    // Approval-guide text is shared by SSO, Subject Officer, and Admin request tables.
    $approvalGuide = [
        'si' => [
            'Approval guide' => 'අනුමැති මාර්ගෝපදේශය',
            'Approval icon meanings' => 'අනුමැති සංකේතවල අර්ථය',
            'Not approved' => 'අනුමත නොකළ',
        ],
        'ta' => [
            'Approval guide' => 'ஒப்புதல் வழிகாட்டி',
            'Approval icon meanings' => 'ஒப்புதல் சின்னங்களின் பொருள்',
            'Not approved' => 'அங்கீகரிக்கப்படவில்லை',
        ],
    ];

    return $sharedActions[$language][$english] ?? $aidRequest[$language][$english] ?? $aidRequestGuidance[$language][$english] ?? $approvalGuide[$language][$english] ?? widmsTranslations()[$language][$english] ?? $english;
}

function renderLanguageSwitcher(string $className = ''): void
{
    $current = widmsLanguage();
    $class = trim('language-switcher ' . $className);
    echo '<nav class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars(t('Language'), ENT_QUOTES, 'UTF-8') . '">';

    // Preserve the current module and filters while changing only the interface language.
    foreach (['si' => 'සිං', 'en' => 'ENG', 'ta' => 'தமிழ்'] as $code => $label) {
        $query = $_GET;
        $query['lang'] = $code;
        $href = '?' . http_build_query($query);
        $active = $current === $code;
        echo '<a class="language-link' . ($active ? ' active' : '') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" lang="' . $code . '"' . ($active ? ' aria-current="true"' : '') . '>' . $label . '</a>';
    }

    echo '</nav>';
}

/**
 * Build the shared browser translation payload used by legacy stakeholder pages.
 * The browser helper changes visible interface copy only; stored business data is untouched.
 */
function widmsUiTranslationPayload(): array
{
    $language = widmsLanguage();
    if ($language === 'en') {
        return [];
    }

    $commonKeys = [
        'Dashboard','Overview','Operations','Requests','Inventory','Reports','Monitoring','System','Config',
        'Search anything...','Search this page','Notifications','Open navigation','Close navigation',
        'Name','Full Name','Username','Email','Phone','Phone Number','Address','District','DS Division','GN Division',
        'Item','Category','Quantity','Status','Reason','Action','Actions','Date','Time','Notes','Description','Reference',
        'Select','Select District','Select DS Division','Select GN Division','Select item','Select status','All Status',
        'Create','Add','Save','Update','Edit','Delete','Cancel','Submit','Approve','Reject','Deactivate','Reactivate','Confirm',
        'Active','Inactive','Pending','Approved','Rejected','Completed','Draft','Distributed','Deactivated',
        'No records found.','No data available.','No recent activity.','Loading...','View details','Sign Out',
        'New Aid Request','My Aid Requests','Location Details','Beneficiary Details','Disability & Aid Requested',
        'Official Approvals','Submit Aid Request','Save as Draft','My Submitted Requests','Aid Requested','Approvals','Submitted',
    ];

    $commonTranslations = [
        'si' => [
            'Name'=>'නම','Email'=>'විද්‍යුත් තැපෑල','Phone'=>'දුරකථනය','Item'=>'අයිතමය','Category'=>'කාණ්ඩය','Quantity'=>'ප්‍රමාණය','Action'=>'ක්‍රියාව','Actions'=>'ක්‍රියා','Date'=>'දිනය','Time'=>'වේලාව','Notes'=>'සටහන්','Description'=>'විස්තරය','Reference'=>'යොමුව','Select'=>'තෝරන්න','Select item'=>'අයිතමය තෝරන්න','Select status'=>'තත්ත්වය තෝරන්න','Create'=>'සාදන්න','Add'=>'එක් කරන්න','Save'=>'සුරකින්න','Update'=>'යාවත්කාලීන කරන්න','Edit'=>'සංස්කරණය','Delete'=>'මකන්න','Cancel'=>'අවලංගු කරන්න','Submit'=>'ඉදිරිපත් කරන්න','Confirm'=>'තහවුරු කරන්න','Inactive'=>'අක්‍රිය','Completed'=>'සම්පූර්ණයි','No records found.'=>'වාර්තා හමු නොවීය.','No data available.'=>'දත්ත නොමැත.','No recent activity.'=>'මෑත ක්‍රියාකාරකම් නොමැත.','Loading...'=>'පූරණය වෙමින්...','Search anything...'=>'ඕනෑම දෙයක් සොයන්න...','Search this page'=>'මෙම පිටුව සොයන්න','Notifications'=>'දැනුම්දීම්','Open navigation'=>'සංචාලනය විවෘත කරන්න','Close navigation'=>'සංචාලනය වසන්න',
        ],
        'ta' => [
            'Name'=>'பெயர்','Email'=>'மின்னஞ்சல்','Phone'=>'தொலைபேசி','Item'=>'பொருள்','Category'=>'வகை','Quantity'=>'அளவு','Action'=>'செயல்','Actions'=>'செயல்கள்','Date'=>'தேதி','Time'=>'நேரம்','Notes'=>'குறிப்புகள்','Description'=>'விவரம்','Reference'=>'குறிப்பு','Select'=>'தேர்ந்தெடுக்கவும்','Select item'=>'பொருளைத் தேர்ந்தெடுக்கவும்','Select status'=>'நிலையைத் தேர்ந்தெடுக்கவும்','Create'=>'உருவாக்கு','Add'=>'சேர்','Save'=>'சேமி','Update'=>'புதுப்பி','Edit'=>'திருத்து','Delete'=>'நீக்கு','Cancel'=>'ரத்துசெய்','Submit'=>'சமர்ப்பி','Confirm'=>'உறுதிப்படுத்து','Inactive'=>'செயலற்றது','Completed'=>'முடிந்தது','No records found.'=>'பதிவுகள் எதுவும் இல்லை.','No data available.'=>'தரவு கிடைக்கவில்லை.','No recent activity.'=>'சமீபத்திய செயல்பாடு இல்லை.','Loading...'=>'ஏற்றுகிறது...','Search anything...'=>'எதையும் தேடுங்கள்...','Search this page'=>'இந்தப் பக்கத்தைத் தேடுங்கள்','Notifications'=>'அறிவிப்புகள்','Open navigation'=>'வழிசெலுத்தலைத் திற','Close navigation'=>'வழிசெலுத்தலை மூடு',
        ],
    ];

    // Social Service Officer pages share these workflow-specific labels and summaries.
    $socialOfficerTranslations = [
        'si' => [
            'Beneficiary Management'=>'ප්‍රතිලාභී කළමනාකරණය','Registered Beneficiaries — My Division'=>'ලියාපදිංචි ප්‍රතිලාභීන් — මගේ කොට්ඨාසය','Search by name or NIC...'=>'නම හෝ හැඳුනුම්පත අනුව සොයන්න...','Disability'=>'ආබාධිත තත්ත්වය','Age'=>'වයස',
            'Distributed Today'=>'අද බෙදා දුන්','Items issued today'=>'අද නිකුත් කළ අයිතම','My Open Requests'=>'මගේ විවෘත ඉල්ලීම්','Pending or approved'=>'පොරොත්තුවෙන් හෝ අනුමත','My Pool Quota (Remaining)'=>'මගේ සංචිත කෝටාව (ඉතිරි)','Returns This Month'=>'මෙම මාසයේ ආපසු භාරදීම්','Returns processed'=>'සකස් කළ ආපසු භාරදීම්','Recent Activity'=>'මෑත ක්‍රියාකාරකම්','By'=>'විසින්','No pool quota allocations available.'=>'සංචිත කෝටා වෙන්කිරීම් නොමැත.',
            'Distribute Items'=>'අයිතම බෙදාහරින්න','Approved requests only'=>'අනුමත ඉල්ලීම් පමණි','Request-Based Distribution'=>'ඉල්ලීම මත බෙදාහැරීම','Approved Request *'=>'අනුමත ඉල්ලීම *','Select approved request'=>'අනුමත ඉල්ලීම තෝරන්න','Approved Quantity *'=>'අනුමත ප්‍රමාණය *','Direct Distribution'=>'සෘජු බෙදාහැරීම','No prior request needed'=>'පෙර ඉල්ලීමක් අවශ්‍ය නොවේ','Beneficiary *'=>'ප්‍රතිලාභියා *','Select beneficiary'=>'ප්‍රතිලාභියා තෝරන්න','Item *'=>'අයිතමය *','Select direct item'=>'සෘජු අයිතමය තෝරන්න','Quantity *'=>'ප්‍රමාණය *','Issue Item Directly'=>'අයිතමය සෘජුව නිකුත් කරන්න','Confirm Distribution'=>'බෙදාහැරීම තහවුරු කරන්න',"Today's Distributions"=>'අද බෙදාහැරීම්','Ref'=>'යොමුව','Type'=>'වර්ගය','Source'=>'මූලාශ්‍රය','Qty'=>'ප්‍රමාණය',
            'Pending Handover'=>'පොරොත්තු භාරදීම','Pending Items'=>'පොරොත්තු අයිතම','Items Handed Over by Subject Officers'=>'විෂය නිලධාරීන් විසින් භාරදුන් අයිතම','Subject Officer'=>'විෂය නිලධාරී','Mark Distributed'=>'බෙදා දුන් ලෙස සලකුණු කරන්න','Pending Contact Lens Handover'=>'පොරොත්තු ස්පර්ශ කාච භාරදීම','Pending Lenses'=>'පොරොත්තු කාච','Individual Lens Units'=>'තනි කාච ඒකක','Lens Unit ID'=>'කාච ඒකක අංකය','Bulk Order'=>'තොග ඇණවුම','Exact Power'=>'නිශ්චිත බලය','Verify identity and exact power'=>'අනන්‍යතාව සහ නිශ්චිත බලය තහවුරු කරන්න','Verify & Distribute'=>'තහවුරු කර බෙදාහරින්න',
            'Pool quota summary'=>'සංචිත කෝටා සාරාංශය','Total Allocated'=>'මුළු වෙන්කිරීම','Available to issue'=>'නිකුත් කිරීමට ඇත','Reused Items'=>'නැවත භාවිත කළ අයිතම','Returned and re-issued'=>'ආපසු ලබා නැවත නිකුත් කළ','Allocated'=>'වෙන් කළ','Remaining'=>'ඉතිරි','Usage'=>'භාවිතය','Variety'=>'ප්‍රභේදය',
            'Return Management'=>'ආපසු භාරදීම් කළමනාකරණය','Distribution Record'=>'බෙදාහැරීමේ වාර්තාව','Select issued item'=>'නිකුත් කළ අයිතමය තෝරන්න','Condition'=>'තත්ත්වය','Select condition'=>'තත්ත්වය තෝරන්න','Good'=>'හොඳ','Damaged'=>'හානි වූ','Unusable'=>'භාවිතයට නුසුදුසු','Restore To'=>'ප්‍රතිස්ථාපනය කරන ස්ථානය','Select destination'=>'ගමනාන්තය තෝරන්න','My Officer Pool'=>'මගේ නිලධාරී සංචිතය','Central Stock'=>'මධ්‍යම තොගය','Process Return'=>'ආපසු භාරදීම සකසන්න','Recent Returns'=>'මෑත ආපසු භාරදීම්','Return ID'=>'ආපසු භාරදීමේ අංකය','Restored To'=>'ප්‍රතිස්ථාපනය කළ ස්ථානය','Reusable'=>'නැවත භාවිත කළ හැකි','Removed / Disposal'=>'ඉවත් කළ / බැහැර කළ',
            'Request status summary'=>'ඉල්ලීම් තත්ත්ව සාරාංශය','Total Submitted'=>'මුළු ඉදිරිපත් කළ','Awaiting Admin'=>'පරිපාලක තීරණය බලාපොරොත්තුවෙන්','With Admin reason'=>'පරිපාලක හේතුව සමඟ','My Request History'=>'මගේ ඉල්ලීම් ඉතිහාසය','All submitted requests'=>'ඉදිරිපත් කළ සියලු ඉල්ලීම්','Request ID'=>'ඉල්ලීම් අංකය','Prescription Power'=>'වෛද්‍ය නිර්දේශිත බලය','Admin Notes'=>'පරිපාලක සටහන්','Report export options'=>'වාර්තා අපනයන විකල්ප',
        ],
        'ta' => [
            'Beneficiary Management'=>'பயனாளி மேலாண்மை','Registered Beneficiaries — My Division'=>'பதிவுசெய்த பயனாளிகள் — எனது பிரிவு','Search by name or NIC...'=>'பெயர் அல்லது அடையாள எண்ணால் தேடவும்...','Disability'=>'மாற்றுத்திறன்','Age'=>'வயது',
            'Distributed Today'=>'இன்று விநியோகிக்கப்பட்டவை','Items issued today'=>'இன்று வழங்கப்பட்ட பொருட்கள்','My Open Requests'=>'எனது திறந்த கோரிக்கைகள்','Pending or approved'=>'நிலுவையில் அல்லது அங்கீகரிக்கப்பட்டவை','My Pool Quota (Remaining)'=>'எனது இருப்புக் கோட்டா (மீதம்)','Returns This Month'=>'இந்த மாதத் திருப்பல்கள்','Returns processed'=>'செயலாக்கப்பட்ட திருப்பல்கள்','Recent Activity'=>'சமீபத்திய செயல்பாடு','By'=>'செய்தவர்','No pool quota allocations available.'=>'இருப்புக் கோட்டா ஒதுக்கீடுகள் இல்லை.',
            'Distribute Items'=>'பொருட்களை விநியோகிக்கவும்','Approved requests only'=>'அங்கீகரிக்கப்பட்ட கோரிக்கைகள் மட்டும்','Request-Based Distribution'=>'கோரிக்கை அடிப்படையிலான விநியோகம்','Approved Request *'=>'அங்கீகரிக்கப்பட்ட கோரிக்கை *','Select approved request'=>'அங்கீகரிக்கப்பட்ட கோரிக்கையைத் தேர்ந்தெடுக்கவும்','Approved Quantity *'=>'அங்கீகரிக்கப்பட்ட அளவு *','Direct Distribution'=>'நேரடி விநியோகம்','No prior request needed'=>'முன் கோரிக்கை தேவையில்லை','Beneficiary *'=>'பயனாளி *','Select beneficiary'=>'பயனாளியைத் தேர்ந்தெடுக்கவும்','Item *'=>'பொருள் *','Select direct item'=>'நேரடி பொருளைத் தேர்ந்தெடுக்கவும்','Quantity *'=>'அளவு *','Issue Item Directly'=>'பொருளை நேரடியாக வழங்கவும்','Confirm Distribution'=>'விநியோகத்தை உறுதிப்படுத்தவும்',"Today's Distributions"=>'இன்றைய விநியோகங்கள்','Ref'=>'குறிப்பு','Type'=>'வகை','Source'=>'மூலம்','Qty'=>'அளவு',
            'Pending Handover'=>'நிலுவை ஒப்படைப்பு','Pending Items'=>'நிலுவைப் பொருட்கள்','Items Handed Over by Subject Officers'=>'விடய அதிகாரிகள் ஒப்படைத்த பொருட்கள்','Subject Officer'=>'விடய அதிகாரி','Mark Distributed'=>'விநியோகிக்கப்பட்டதாகக் குறிக்கவும்','Pending Contact Lens Handover'=>'நிலுவை தொடு வில்லை ஒப்படைப்பு','Pending Lenses'=>'நிலுவை வில்லைகள்','Individual Lens Units'=>'தனி வில்லை அலகுகள்','Lens Unit ID'=>'வில்லை அலகு எண்','Bulk Order'=>'மொத்த ஆணை','Exact Power'=>'சரியான வலிமை','Verify identity and exact power'=>'அடையாளத்தையும் சரியான வலிமையையும் சரிபார்க்கவும்','Verify & Distribute'=>'சரிபார்த்து விநியோகிக்கவும்',
            'Pool quota summary'=>'இருப்புக் கோட்டா சுருக்கம்','Total Allocated'=>'மொத்த ஒதுக்கீடு','Available to issue'=>'வழங்கக் கிடைக்கும்','Reused Items'=>'மீண்டும் பயன்படுத்திய பொருட்கள்','Returned and re-issued'=>'திரும்பப் பெற்று மீண்டும் வழங்கியவை','Allocated'=>'ஒதுக்கப்பட்டது','Remaining'=>'மீதம்','Usage'=>'பயன்பாடு','Variety'=>'வகை',
            'Return Management'=>'திருப்பல் மேலாண்மை','Distribution Record'=>'விநியோகப் பதிவு','Select issued item'=>'வழங்கிய பொருளைத் தேர்ந்தெடுக்கவும்','Condition'=>'நிலைமை','Select condition'=>'நிலைமையைத் தேர்ந்தெடுக்கவும்','Good'=>'நல்லது','Damaged'=>'சேதமடைந்தது','Unusable'=>'பயன்படுத்த முடியாதது','Restore To'=>'மீட்டமைக்கும் இடம்','Select destination'=>'இலக்கைத் தேர்ந்தெடுக்கவும்','My Officer Pool'=>'எனது அதிகாரி இருப்பு','Central Stock'=>'மத்திய கையிருப்பு','Process Return'=>'திருப்பலைச் செயலாக்கவும்','Recent Returns'=>'சமீபத்திய திருப்பல்கள்','Return ID'=>'திருப்பல் எண்','Restored To'=>'மீட்டமைக்கப்பட்ட இடம்','Reusable'=>'மீண்டும் பயன்படுத்தக்கூடியது','Removed / Disposal'=>'நீக்கப்பட்டது / அகற்றப்பட்டது',
            'Request status summary'=>'கோரிக்கை நிலைச் சுருக்கம்','Total Submitted'=>'மொத்தம் சமர்ப்பித்தவை','Awaiting Admin'=>'நிர்வாகிக்காக காத்திருக்கிறது','With Admin reason'=>'நிர்வாகியின் காரணத்துடன்','My Request History'=>'எனது கோரிக்கை வரலாறு','All submitted requests'=>'சமர்ப்பித்த அனைத்து கோரிக்கைகளும்','Request ID'=>'கோரிக்கை எண்','Prescription Power'=>'மருத்துவப் பரிந்துரை வலிமை','Admin Notes'=>'நிர்வாகி குறிப்புகள்','Report export options'=>'அறிக்கை ஏற்றுமதி விருப்பங்கள்',
        ],
    ];

    // Shared terminology for Administrator, Subject Officer, and Store Keeper workflows.
    $stakeholderTranslations = [
        'si' => [
            'User Management'=>'පරිශීලක කළමනාකරණය','Pending Approvals'=>'පොරොත්තු අනුමැතීන්','Users'=>'පරිශීලකයින්','Role'=>'භූමිකාව','Division'=>'කොට්ඨාසය','Contact'=>'සම්බන්ධතා','Created'=>'සාදන ලදී','Updated'=>'යාවත්කාලීන කරන ලදී','Last Login'=>'අවසන් පිවිසුම','Activate'=>'සක්‍රිය කරන්න','Reset Password'=>'මුරපදය යළි සකසන්න','View'=>'බලන්න','Review'=>'සමාලෝචනය','Details'=>'විස්තර','Search'=>'සොයන්න','Filter'=>'පෙරන්න','All'=>'සියල්ල','From'=>'සිට','To'=>'දක්වා','Start Date'=>'ආරම්භක දිනය','End Date'=>'අවසන් දිනය',
            'Current Stock'=>'වත්මන් තොගය','Central Stock'=>'මධ්‍යම තොගය','Receive Items'=>'අයිතම භාරගන්න','Dispatch'=>'නිකුත් කිරීම','Recently Dispatched'=>'මෑතදී නිකුත් කළ','Available Stock'=>'පවතින තොගය','Stock Level'=>'තොග මට්ටම','Low Stock'=>'අඩු තොගය','Unit'=>'ඒකකය','Batch'=>'තොග කාණ්ඩය','Batch Number'=>'තොග කාණ්ඩ අංකය','Received Date'=>'භාරගත් දිනය','Received By'=>'භාරගත්තේ','Dispatched Date'=>'නිකුත් කළ දිනය','Dispatched By'=>'නිකුත් කළේ','Destination'=>'ගමනාන්තය','Select destination'=>'ගමනාන්තය තෝරන්න','Record Receipt'=>'භාරගැනීම සටහන් කරන්න','Approved Requests Ready for Dispatch'=>'නිකුත් කිරීමට සූදානම් අනුමත ඉල්ලීම්',
            'Supplier'=>'සැපයුම්කරු','Suppliers'=>'සැපයුම්කරුවන්','Company'=>'සමාගම','Company Name'=>'සමාගමේ නම','Payment'=>'ගෙවීම','Payments'=>'ගෙවීම්','Amount'=>'මුදල','Paid Amount'=>'ගෙවූ මුදල','Balance'=>'ශේෂය','Outstanding Balance'=>'හිඟ ශේෂය','Payment Date'=>'ගෙවූ දිනය','Payment Method'=>'ගෙවීම් ක්‍රමය','Invoice'=>'ඉන්වොයිසිය','Invoice Number'=>'ඉන්වොයිසි අංකය','Purchase Price'=>'මිලදී ගැනීමේ මිල','Total'=>'එකතුව','Total Amount'=>'මුළු මුදල',
            'Requested By'=>'ඉල්ලා සිටියේ','Approved By'=>'අනුමත කළේ','Reviewed By'=>'සමාලෝචනය කළේ','Submitted By'=>'ඉදිරිපත් කළේ','Reason / Justification'=>'හේතුව / සාධාරණීකරණය','Justification'=>'සාධාරණීකරණය','Decision'=>'තීරණය','Approval'=>'අනුමැතිය','Correction Request'=>'නිවැරදි කිරීමේ ඉල්ලීම','Correction Requests'=>'නිවැරදි කිරීමේ ඉල්ලීම්','Original Value'=>'මුල් අගය','Corrected Value'=>'නිවැරදි කළ අගය','Audit Log'=>'විගණන සටහන','Activity'=>'ක්‍රියාකාරකම','Module'=>'මොඩියුලය','Export'=>'අපනයනය','Generate Report'=>'වාර්තාව සාදන්න','Download'=>'බාගන්න','Print'=>'මුද්‍රණය',
            'Eligibility Rules'=>'සුදුසුකම් නීති','Item Categories'=>'අයිතම කාණ්ඩ','Category Name'=>'කාණ්ඩ නම','Minimum Age'=>'අවම වයස','Maximum Age'=>'උපරිම වයස','Enabled'=>'සක්‍රියයි','Disabled'=>'අක්‍රියයි','System Configuration'=>'පද්ධති සැකසුම්','Setting'=>'සැකසුම','Value'=>'අගය','Save Changes'=>'වෙනස්කම් සුරකින්න','Back'=>'ආපසු','Next'=>'ඊළඟ','Previous'=>'පෙර','Required'=>'අවශ්‍යයි','Optional'=>'විකල්පයි',
        ],
        'ta' => [
            'User Management'=>'பயனர் மேலாண்மை','Pending Approvals'=>'நிலுவை ஒப்புதல்கள்','Users'=>'பயனர்கள்','Role'=>'பங்கு','Division'=>'பிரிவு','Contact'=>'தொடர்பு','Created'=>'உருவாக்கப்பட்டது','Updated'=>'புதுப்பிக்கப்பட்டது','Last Login'=>'கடைசி உள்நுழைவு','Activate'=>'செயல்படுத்து','Reset Password'=>'கடவுச்சொல்லை மீட்டமை','View'=>'பார்','Review'=>'மதிப்பாய்வு','Details'=>'விவரங்கள்','Search'=>'தேடு','Filter'=>'வடிகட்டு','All'=>'அனைத்தும்','From'=>'இருந்து','To'=>'வரை','Start Date'=>'தொடக்க தேதி','End Date'=>'முடிவு தேதி',
            'Current Stock'=>'தற்போதைய கையிருப்பு','Central Stock'=>'மத்திய கையிருப்பு','Receive Items'=>'பொருட்களைப் பெறுக','Dispatch'=>'அனுப்புதல்','Recently Dispatched'=>'சமீபத்தில் அனுப்பப்பட்டவை','Available Stock'=>'கிடைக்கும் கையிருப்பு','Stock Level'=>'கையிருப்பு நிலை','Low Stock'=>'குறைந்த கையிருப்பு','Unit'=>'அலகு','Batch'=>'தொகுதி','Batch Number'=>'தொகுதி எண்','Received Date'=>'பெற்ற தேதி','Received By'=>'பெற்றவர்','Dispatched Date'=>'அனுப்பிய தேதி','Dispatched By'=>'அனுப்பியவர்','Destination'=>'இலக்கு','Select destination'=>'இலக்கைத் தேர்ந்தெடுக்கவும்','Record Receipt'=>'பெறுதலைப் பதிவு செய்க','Approved Requests Ready for Dispatch'=>'அனுப்பத் தயாரான அங்கீகரிக்கப்பட்ட கோரிக்கைகள்',
            'Supplier'=>'வழங்குநர்','Suppliers'=>'வழங்குநர்கள்','Company'=>'நிறுவனம்','Company Name'=>'நிறுவனப் பெயர்','Payment'=>'கொடுப்பனவு','Payments'=>'கொடுப்பனவுகள்','Amount'=>'தொகை','Paid Amount'=>'செலுத்திய தொகை','Balance'=>'மீதம்','Outstanding Balance'=>'நிலுவைத் தொகை','Payment Date'=>'கொடுப்பனவு தேதி','Payment Method'=>'கொடுப்பனவு முறை','Invoice'=>'விலைப்பட்டியல்','Invoice Number'=>'விலைப்பட்டியல் எண்','Purchase Price'=>'கொள்வனவு விலை','Total'=>'மொத்தம்','Total Amount'=>'மொத்தத் தொகை',
            'Requested By'=>'கோரியவர்','Approved By'=>'அங்கீகரித்தவர்','Reviewed By'=>'மதிப்பாய்வு செய்தவர்','Submitted By'=>'சமர்ப்பித்தவர்','Reason / Justification'=>'காரணம் / நியாயம்','Justification'=>'நியாயம்','Decision'=>'தீர்மானம்','Approval'=>'ஒப்புதல்','Correction Request'=>'திருத்தக் கோரிக்கை','Correction Requests'=>'திருத்தக் கோரிக்கைகள்','Original Value'=>'மூல மதிப்பு','Corrected Value'=>'திருத்திய மதிப்பு','Audit Log'=>'தணிக்கைப் பதிவு','Activity'=>'செயல்பாடு','Module'=>'தொகுதி','Export'=>'ஏற்றுமதி','Generate Report'=>'அறிக்கையை உருவாக்கு','Download'=>'பதிவிறக்கு','Print'=>'அச்சிடு',
            'Eligibility Rules'=>'தகுதி விதிகள்','Item Categories'=>'பொருள் வகைகள்','Category Name'=>'வகைப் பெயர்','Minimum Age'=>'குறைந்தபட்ச வயது','Maximum Age'=>'அதிகபட்ச வயது','Enabled'=>'இயக்கப்பட்டது','Disabled'=>'முடக்கப்பட்டது','System Configuration'=>'கணினி அமைப்பு','Setting'=>'அமைப்பு','Value'=>'மதிப்பு','Save Changes'=>'மாற்றங்களைச் சேமி','Back'=>'பின்செல்','Next'=>'அடுத்து','Previous'=>'முந்தையது','Required'=>'தேவை','Optional'=>'விருப்பம்',
        ],
    ];

    // Administrator-specific phrases cover complete headings, workflows, and empty states.
    $adminTranslations = [
        'si' => [
            'System statistics'=>'පද්ධති සංඛ්‍යාලේඛන','Total Central Stock'=>'මුළු මධ්‍යම තොගය','Across all approval queues'=>'සියලු අනුමැති පෝලිම් තුළ','Active Social Service Officers'=>'සක්‍රිය සමාජ සේවා නිලධාරීන්','Active distributors'=>'සක්‍රිය බෙදාහරින්නන්','Distributions Today'=>'අද බෙදාහැරීම්','Pending Actions'=>'පොරොත්තු ක්‍රියා','View All'=>'සියල්ල බලන්න','System Overview'=>'පද්ධති දළ විශ්ලේෂණය','Central Stock Items'=>'මධ්‍යම තොග අයිතම','Total Beneficiaries'=>'මුළු ප්‍රතිලාභීන්','Districts Covered'=>'ආවරණය කරන දිස්ත්‍රික්ක','Distributions This Month'=>'මෙම මාසයේ බෙදාහැරීම්',
            'User Registrations'=>'පරිශීලක ලියාපදිංචි කිරීම්','Item Requests'=>'අයිතම ඉල්ලීම්','Stock Release'=>'තොග නිකුතුව','User Registration Requests'=>'පරිශීලක ලියාපදිංචි ඉල්ලීම්','Applicant'=>'අයදුම්කරු','Approval categories'=>'අනුමැති කාණ්ඩ','Stock Release Requests'=>'තොග නිකුතු ඉල්ලීම්','No registration requests available.'=>'ලියාපදිංචි ඉල්ලීම් නොමැත.','Beneficiary Registration Requests'=>'ප්‍රතිලාභී ලියාපදිංචි ඉල්ලීම්','Name / NIC'=>'නම / හැඳුනුම්පත','Location'=>'ස්ථානය','No beneficiary registration requests.'=>'ප්‍රතිලාභී ලියාපදිංචි ඉල්ලීම් නොමැත.','Official Beneficiary Registry'=>'නිල ප්‍රතිලාභී ලේඛනය','No approved beneficiaries.'=>'අනුමත ප්‍රතිලාභීන් නොමැත.','Reason required for rejection'=>'ප්‍රතික්ෂේප කිරීමට හේතුව අවශ්‍යයි','Reviewed'=>'සමාලෝචනය කරන ලදී',
            'Central Stock Inventory'=>'මධ්‍යම තොග ලේඛනය','In Stock'=>'තොගයේ ඇත','Payment Status'=>'ගෙවීම් තත්ත්වය','No central stock items available.'=>'මධ්‍යම තොග අයිතම නොමැත.','Stock availability and supplier payment status are tracked independently.'=>'තොග පවතින බව සහ සැපයුම්කරුගේ ගෙවීම් තත්ත්වය වෙන වෙනම සටහන් කරයි.','Standard'=>'සම්මත',
            'Pending Admin Approval'=>'පරිපාලක අනුමැතිය බලාපොරොත්තුවෙන්','Approved — Awaiting Dispatch'=>'අනුමතයි — නිකුතුව බලාපොරොත්තුවෙන්','Dispatched'=>'නිකුත් කරන ලදී','Live request count'=>'සජීවී ඉල්ලීම් ගණන','All Goods Requests'=>'සියලු භාණ්ඩ ඉල්ලීම්','No goods requests available.'=>'භාණ්ඩ ඉල්ලීම් නොමැත.','Reason for rejection'=>'ප්‍රතික්ෂේප කිරීමේ හේතුව','Central Stock'=>'මධ්‍යම තොගය',
            'Total Officers'=>'මුළු නිලධාරීන්','Total Distributed'=>'මුළු බෙදාහැරීම්','Total Remaining'=>'මුළු ඉතිරිය','Live pool total'=>'සජීවී සංචිත එකතුව','Assign Officer Division'=>'නිලධාරියාට කොට්ඨාසය පවරන්න','Officer'=>'නිලධාරියා','Select officer'=>'නිලධාරියා තෝරන්න','Select division'=>'කොට්ඨාසය තෝරන්න','Assign Division'=>'කොට්ඨාසය පවරන්න','Allocate Division Stock'=>'කොට්ඨාස තොගය වෙන් කරන්න','Available Division Stock'=>'පවතින කොට්ඨාස තොගය','Allocate to Pool'=>'සංචිතයට වෙන් කරන්න','Officer Pool Balances'=>'නිලධාරී සංචිත ශේෂ','Reused'=>'නැවත භාවිත කළ','No officer pool allocations available.'=>'නිලධාරී සංචිත වෙන්කිරීම් නොමැත.','Unassigned'=>'පවරා නොමැත','Empty'=>'හිස්','Low'=>'අඩු','OK'=>'හරි',
            'Supplier Payments'=>'සැපයුම්කරුගේ ගෙවීම්','Supplier Balance Summary'=>'සැපයුම්කරුගේ ශේෂ සාරාංශය','No supplier balances available.'=>'සැපයුම්කරුගේ ශේෂ නොමැත.','Invoiced:'=>'ඉන්වොයිස් කළ:','Paid:'=>'ගෙවූ:','Record Payment'=>'ගෙවීම සටහන් කරන්න','Outstanding Procurement Batch'=>'හිඟ ප්‍රසම්පාදන තොග කාණ්ඩය','Select batch'=>'තොග කාණ්ඩය තෝරන්න','Amount Paid (Rs)'=>'ගෙවූ මුදල (රු.)','Check Number'=>'චෙක්පත් අංකය','Check Date'=>'චෙක්පත් දිනය','Payment History'=>'ගෙවීම් ඉතිහාසය','Payment ID'=>'ගෙවීම් අංකය','Bill No.'=>'බිල් අංකය','Check No.'=>'චෙක්පත් අංකය','Recorded By'=>'සටහන් කළේ','No payment history available.'=>'ගෙවීම් ඉතිහාසයක් නොමැත.',
            'All Users'=>'සියලු පරිශීලකයින්','All Modules'=>'සියලු මොඩියුල','Clear'=>'හිස් කරන්න','Timestamp'=>'කාල මුද්‍රාව','User'=>'පරිශීලකයා','Record Ref'=>'වාර්තා යොමුව','No audit log entries available.'=>'විගණන සටහන් නොමැත.','Generate'=>'සාදන්න','Print / Save PDF'=>'මුද්‍රණය / PDF ලෙස සුරකින්න','No records available for this report.'=>'මෙම වාර්තාව සඳහා වාර්තා නොමැත.',
            'General Settings'=>'සාමාන්‍ය සැකසුම්','Notification Settings'=>'දැනුම්දීම් සැකසුම්','Disability Types'=>'ආබාධිත වර්ග','Add Type'=>'වර්ගය එක් කරන්න','e.g. Psychosocial Disability'=>'උදා: මනෝසමාජීය ආබාධිත තත්ත්වය','Add User'=>'පරිශීලකයෙකු එක් කරන්න','System users'=>'පද්ධති පරිශීලකයින්','Suspend'=>'අත්හිටුවන්න','You cannot suspend your own account'=>'ඔබගේම ගිණුම අත්හිටුවිය නොහැක',
            'Awaiting Vendor Approval'=>'සැපයුම්කරුගේ අනුමැතිය බලාපොරොත්තුවෙන්','Awaiting Goods Release'=>'භාණ්ඩ නිකුතුව බලාපොරොත්තුවෙන්','Distribution in Progress'=>'බෙදාහැරීම සිදු වෙමින්','Live camp count'=>'සජීවී කඳවුරු ගණන','All Vision Camp Requests'=>'සියලු දෘෂ්ටි කඳවුරු ඉල්ලීම්','Vendor'=>'සැපයුම්කරු','Camp Date'=>'කඳවුරු දිනය','Identified'=>'හඳුනාගත්','Attended'=>'සහභාගී වූ','Stage'=>'අදියර','No Vision Camp requests available.'=>'දෘෂ්ටි කඳවුරු ඉල්ලීම් නොමැත.','Rejection reason'=>'ප්‍රතික්ෂේප කිරීමේ හේතුව','Store Keeper Correction Requests'=>'ගබඩා භාරකරුගේ නිවැරදි කිරීමේ ඉල්ලීම්','Admin reason / note'=>'පරිපාලක හේතුව / සටහන','Required when rejecting the request'=>'ඉල්ලීම ප්‍රතික්ෂේප කරන විට අවශ්‍යයි',
        ],
        'ta' => [
            'System statistics'=>'கணினிப் புள்ளிவிவரங்கள்','Total Central Stock'=>'மொத்த மத்திய கையிருப்பு','Across all approval queues'=>'அனைத்து ஒப்புதல் வரிசைகளிலும்','Active Social Service Officers'=>'செயலில் உள்ள சமூக சேவை அதிகாரிகள்','Active distributors'=>'செயலில் உள்ள விநியோகிப்போர்','Distributions Today'=>'இன்றைய விநியோகங்கள்','Pending Actions'=>'நிலுவைச் செயல்கள்','View All'=>'அனைத்தையும் பார்','System Overview'=>'கணினி மேலோட்டம்','Central Stock Items'=>'மத்திய கையிருப்புப் பொருட்கள்','Total Beneficiaries'=>'மொத்த பயனாளிகள்','Districts Covered'=>'உள்ளடக்கப்பட்ட மாவட்டங்கள்','Distributions This Month'=>'இந்த மாத விநியோகங்கள்',
            'User Registrations'=>'பயனர் பதிவுகள்','Item Requests'=>'பொருள் கோரிக்கைகள்','Stock Release'=>'கையிருப்பு வெளியீடு','User Registration Requests'=>'பயனர் பதிவுக் கோரிக்கைகள்','Applicant'=>'விண்ணப்பதாரர்','Approval categories'=>'ஒப்புதல் வகைகள்','Stock Release Requests'=>'கையிருப்பு வெளியீட்டுக் கோரிக்கைகள்','No registration requests available.'=>'பதிவுக் கோரிக்கைகள் இல்லை.','Beneficiary Registration Requests'=>'பயனாளி பதிவுக் கோரிக்கைகள்','Name / NIC'=>'பெயர் / அடையாள எண்','Location'=>'இருப்பிடம்','No beneficiary registration requests.'=>'பயனாளி பதிவுக் கோரிக்கைகள் இல்லை.','Official Beneficiary Registry'=>'அதிகாரப்பூர்வ பயனாளிப் பதிவேடு','No approved beneficiaries.'=>'அங்கீகரிக்கப்பட்ட பயனாளிகள் இல்லை.','Reason required for rejection'=>'நிராகரிப்பதற்கான காரணம் தேவை','Reviewed'=>'மதிப்பாய்வு செய்யப்பட்டது',
            'Central Stock Inventory'=>'மத்திய கையிருப்புப் பட்டியல்','In Stock'=>'கையிருப்பில்','Payment Status'=>'கொடுப்பனவு நிலை','No central stock items available.'=>'மத்திய கையிருப்புப் பொருட்கள் இல்லை.','Stock availability and supplier payment status are tracked independently.'=>'கையிருப்பு இருப்பும் வழங்குநர் கொடுப்பனவு நிலையும் தனித்தனியாகப் பதிவு செய்யப்படுகின்றன.','Standard'=>'தரநிலை',
            'Pending Admin Approval'=>'நிர்வாகி ஒப்புதலுக்காக நிலுவையில்','Approved — Awaiting Dispatch'=>'அங்கீகரிக்கப்பட்டது — அனுப்பக் காத்திருக்கிறது','Dispatched'=>'அனுப்பப்பட்டது','Live request count'=>'நேரடி கோரிக்கை எண்ணிக்கை','All Goods Requests'=>'அனைத்து பொருள் கோரிக்கைகள்','No goods requests available.'=>'பொருள் கோரிக்கைகள் இல்லை.','Reason for rejection'=>'நிராகரிப்பதற்கான காரணம்',
            'Total Officers'=>'மொத்த அதிகாரிகள்','Total Distributed'=>'மொத்த விநியோகம்','Total Remaining'=>'மொத்த மீதி','Live pool total'=>'நேரடி இருப்பு மொத்தம்','Assign Officer Division'=>'அதிகாரிக்குப் பிரிவை ஒதுக்கு','Officer'=>'அதிகாரி','Select officer'=>'அதிகாரியைத் தேர்ந்தெடுக்கவும்','Select division'=>'பிரிவைத் தேர்ந்தெடுக்கவும்','Assign Division'=>'பிரிவை ஒதுக்கு','Allocate Division Stock'=>'பிரிவுக் கையிருப்பை ஒதுக்கு','Available Division Stock'=>'கிடைக்கும் பிரிவுக் கையிருப்பு','Allocate to Pool'=>'இருப்புக்கு ஒதுக்கு','Officer Pool Balances'=>'அதிகாரி இருப்பு மீதிகள்','Reused'=>'மீண்டும் பயன்படுத்தப்பட்டது','No officer pool allocations available.'=>'அதிகாரி இருப்பு ஒதுக்கீடுகள் இல்லை.','Unassigned'=>'ஒதுக்கப்படவில்லை','Empty'=>'காலி','Low'=>'குறைவு','OK'=>'சரி',
            'Supplier Payments'=>'வழங்குநர் கொடுப்பனவுகள்','Supplier Balance Summary'=>'வழங்குநர் மீதிச் சுருக்கம்','No supplier balances available.'=>'வழங்குநர் மீதிகள் இல்லை.','Invoiced:'=>'விலைப்பட்டியல்:','Paid:'=>'செலுத்தியது:','Record Payment'=>'கொடுப்பனவைப் பதிவு செய்','Outstanding Procurement Batch'=>'நிலுவை கொள்வனவுத் தொகுதி','Select batch'=>'தொகுதியைத் தேர்ந்தெடுக்கவும்','Amount Paid (Rs)'=>'செலுத்திய தொகை (ரூ.)','Check Number'=>'காசோலை எண்','Check Date'=>'காசோலை தேதி','Payment History'=>'கொடுப்பனவு வரலாறு','Payment ID'=>'கொடுப்பனவு எண்','Bill No.'=>'பில் எண்','Check No.'=>'காசோலை எண்','Recorded By'=>'பதிவு செய்தவர்','No payment history available.'=>'கொடுப்பனவு வரலாறு இல்லை.',
            'All Users'=>'அனைத்து பயனர்களும்','All Modules'=>'அனைத்து தொகுதிகளும்','Clear'=>'அழி','Timestamp'=>'நேரமுத்திரை','User'=>'பயனர்','Record Ref'=>'பதிவுக் குறிப்பு','No audit log entries available.'=>'தணிக்கைப் பதிவுகள் இல்லை.','Generate'=>'உருவாக்கு','Print / Save PDF'=>'அச்சிடு / PDF ஆகச் சேமி','No records available for this report.'=>'இந்த அறிக்கைக்குப் பதிவுகள் இல்லை.',
            'General Settings'=>'பொது அமைப்புகள்','Notification Settings'=>'அறிவிப்பு அமைப்புகள்','Disability Types'=>'மாற்றுத்திறன் வகைகள்','Add Type'=>'வகையைச் சேர்','e.g. Psychosocial Disability'=>'உதா: உளசமூக மாற்றுத்திறன்','Add User'=>'பயனரைச் சேர்','System users'=>'கணினிப் பயனர்கள்','Suspend'=>'இடைநிறுத்து','You cannot suspend your own account'=>'உங்கள் சொந்த கணக்கை இடைநிறுத்த முடியாது',
            'Awaiting Vendor Approval'=>'வழங்குநர் ஒப்புதலுக்காகக் காத்திருக்கிறது','Awaiting Goods Release'=>'பொருள் வெளியீட்டுக்காகக் காத்திருக்கிறது','Distribution in Progress'=>'விநியோகம் நடைபெறுகிறது','Live camp count'=>'நேரடி முகாம் எண்ணிக்கை','All Vision Camp Requests'=>'அனைத்து பார்வை முகாம் கோரிக்கைகள்','Vendor'=>'வழங்குநர்','Camp Date'=>'முகாம் தேதி','Identified'=>'அடையாளம் காணப்பட்டோர்','Attended'=>'கலந்துகொண்டோர்','Stage'=>'கட்டம்','No Vision Camp requests available.'=>'பார்வை முகாம் கோரிக்கைகள் இல்லை.','Rejection reason'=>'நிராகரிப்பதற்கான காரணம்','Store Keeper Correction Requests'=>'களஞ்சியப் பொறுப்பாளர் திருத்தக் கோரிக்கைகள்','Admin reason / note'=>'நிர்வாகி காரணம் / குறிப்பு','Required when rejecting the request'=>'கோரிக்கையை நிராகரிக்கும்போது தேவை',
        ],
    ];

    // Contact-lens workflow phrases are grouped here because the overview is shared by several roles.
    $contactLensTranslations = [
        'si' => [
            'Contact Lens Orders'=>'ස්පර්ශ කාච ඇණවුම්',
            'Contact Lens Stock — By Power'=>'බලය අනුව ස්පර්ශ කාච තොගය',
            'All Contact Lens Orders'=>'සියලු ස්පර්ශ කාච ඇණවුම්',
            'Power'=>'බලය', 'In Stock'=>'තොගයේ ඇත', 'Company'=>'සමාගම', 'Last Received'=>'අවසන් වරට ලැබුණු දිනය',
            'No contact lens stock records available.'=>'ස්පර්ශ කාච තොග වාර්තා නොමැත.',
            'Available'=>'ලබා ගත හැක', 'Out of Stock'=>'තොග අවසන්',
            'Search name, NIC or power...'=>'නම, හැඳුනුම්පත් අංකය හෝ බලය සොයන්න...',
            'Search contact lens orders'=>'ස්පර්ශ කාච ඇණවුම් සොයන්න',
            'Beneficiary'=>'ප්‍රතිලාභියා', 'Division'=>'කොට්ඨාසය', 'Requested Power'=>'ඉල්ලූ බලය',
            'Current Power'=>'වත්මන් බලය', 'Power Changed?'=>'බලය වෙනස් වී තිබේද?', 'Stock Check'=>'තොග පරීක්ෂාව',
            'Changed'=>'වෙනස් වී ඇත', 'Same'=>'එකමය', 'Not checked'=>'පරීක්ෂා කර නැත',
            'Pending'=>'පොරොත්තුවෙන්', 'Approved'=>'අනුමතයි', 'Rejected'=>'ප්‍රතික්ෂේපිතයි',
            'Pending Admin Approval'=>'පරිපාලක අනුමැතිය බලාපොරොත්තුවෙන්',
            'Partially Received'=>'අර්ධ වශයෙන් ලැබී ඇත', 'Fully Received'=>'සම්පූර්ණයෙන් ලැබී ඇත',
            'Completed'=>'සම්පූර්ණයි', 'Draft'=>'කෙටුම්පත',
            'No contact lens orders available.'=>'ස්පර්ශ කාච ඇණවුම් නොමැත.',
            'Rejection reason'=>'ප්‍රතික්ෂේප කිරීමට හේතුව', 'Approve'=>'අනුමත කරන්න', 'Reject'=>'ප්‍රතික්ෂේප කරන්න', 'View'=>'බලන්න',
            'Your session expired.'=>'ඔබගේ සැසිය කල් ඉකුත් වී ඇත.',
            'Enter a valid decision and a reason when rejecting.'=>'වලංගු තීරණයක් තෝරා, ප්‍රතික්ෂේප කරන්නේ නම් හේතුවක් ඇතුළත් කරන්න.',
            'This contact lens request was already reviewed or does not exist.'=>'මෙම ස්පර්ශ කාච ඉල්ලීම දැනටමත් සමාලෝචනය කර ඇත හෝ එය නොපවතී.',
            'Contact lens request approved.'=>'ස්පර්ශ කාච ඉල්ලීම අනුමත කරන ලදී.',
            'Contact lens request rejected.'=>'ස්පර්ශ කාච ඉල්ලීම ප්‍රතික්ෂේප කරන ලදී.',
            'Enter a valid decision and rejection reason when rejecting.'=>'වලංගු තීරණයක් සහ ප්‍රතික්ෂේප කිරීමට හේතුව ඇතුළත් කරන්න.',
            'Order is no longer awaiting Gate 2 review.'=>'ඇණවුම තවදුරටත් දෙවන අදියරේ සමාලෝචනය බලාපොරොත්තුවෙන් නොමැත.',
            'An empty bulk order cannot be approved.'=>'හිස් තොග ඇණවුමක් අනුමත කළ නොහැක.',
            'Bulk order approved.'=>'තොග ඇණවුම අනුමත කරන ලදී.', 'Bulk order rejected.'=>'තොග ඇණවුම ප්‍රතික්ෂේප කරන ලදී.',
            'Enter a valid receipt quantity.'=>'වලංගු ලැබීම් ප්‍රමාණයක් ඇතුළත් කරන්න.',
            'This order is not approved for receipt.'=>'මෙම ඇණවුම භාරගැනීම සඳහා අනුමත කර නැත.',
            'Invalid workflow action.'=>'වලංගු නොවන කාර්ය ප්‍රවාහ ක්‍රියාවකි.',
            'Unable to update workflow.'=>'කාර්ය ප්‍රවාහය යාවත්කාලීන කළ නොහැක.',
            'Bulk workflow unavailable. Run migrations first.'=>'තොග කාර්ය ප්‍රවාහය ලබාගත නොහැක. පළමුව දත්ත සමුදා සංක්‍රමණ ක්‍රියාත්මක කරන්න.',
        ],
        'ta' => [
            'Contact Lens Orders'=>'தொடர்பு வில்லை ஆணைகள்',
            'Contact Lens Stock — By Power'=>'வலிமை அடிப்படையிலான தொடர்பு வில்லை கையிருப்பு',
            'All Contact Lens Orders'=>'அனைத்து தொடர்பு வில்லை ஆணைகள்',
            'Power'=>'வலிமை', 'In Stock'=>'கையிருப்பில்', 'Company'=>'நிறுவனம்', 'Last Received'=>'கடைசியாகப் பெற்றது',
            'No contact lens stock records available.'=>'தொடர்பு வில்லை கையிருப்புப் பதிவுகள் இல்லை.',
            'Available'=>'கிடைக்கிறது', 'Out of Stock'=>'கையிருப்பு இல்லை',
            'Search name, NIC or power...'=>'பெயர், அடையாள அட்டை எண் அல்லது வலிமையைத் தேடுக...',
            'Search contact lens orders'=>'தொடர்பு வில்லை ஆணைகளைத் தேடுக',
            'Beneficiary'=>'பயனாளர்', 'Division'=>'பிரிவு', 'Requested Power'=>'கோரிய வலிமை',
            'Current Power'=>'தற்போதைய வலிமை', 'Power Changed?'=>'வலிமை மாற்றப்பட்டதா?', 'Stock Check'=>'கையிருப்புச் சரிபார்ப்பு',
            'Changed'=>'மாற்றப்பட்டது', 'Same'=>'மாற்றமில்லை', 'Not checked'=>'சரிபார்க்கப்படவில்லை',
            'Pending'=>'நிலுவையில்', 'Approved'=>'அங்கீகரிக்கப்பட்டது', 'Rejected'=>'நிராகரிக்கப்பட்டது',
            'Pending Admin Approval'=>'நிர்வாகி அங்கீகாரத்திற்காக நிலுவையில்',
            'Partially Received'=>'பகுதியளவில் பெறப்பட்டது', 'Fully Received'=>'முழுமையாகப் பெறப்பட்டது',
            'Completed'=>'நிறைவடைந்தது', 'Draft'=>'வரைவு',
            'No contact lens orders available.'=>'தொடர்பு வில்லை ஆணைகள் இல்லை.',
            'Rejection reason'=>'நிராகரிப்பதற்கான காரணம்', 'Approve'=>'அங்கீகரி', 'Reject'=>'நிராகரி', 'View'=>'பார்க்க',
            'Your session expired.'=>'உங்கள் அமர்வு காலாவதியானது.',
            'Enter a valid decision and a reason when rejecting.'=>'செல்லுபடியாகும் முடிவைத் தேர்ந்து, நிராகரிக்கும்போது காரணத்தை உள்ளிடவும்.',
            'This contact lens request was already reviewed or does not exist.'=>'இந்த தொடர்பு வில்லை கோரிக்கை ஏற்கனவே மதிப்பாய்வு செய்யப்பட்டுள்ளது அல்லது இல்லை.',
            'Contact lens request approved.'=>'தொடர்பு வில்லை கோரிக்கை அங்கீகரிக்கப்பட்டது.',
            'Contact lens request rejected.'=>'தொடர்பு வில்லை கோரிக்கை நிராகரிக்கப்பட்டது.',
            'Enter a valid decision and rejection reason when rejecting.'=>'செல்லுபடியாகும் முடிவையும் நிராகரிப்பதற்கான காரணத்தையும் உள்ளிடவும்.',
            'Order is no longer awaiting Gate 2 review.'=>'ஆணை இனி இரண்டாம் கட்ட மதிப்பாய்வுக்காகக் காத்திருக்கவில்லை.',
            'An empty bulk order cannot be approved.'=>'காலியான மொத்த ஆணையை அங்கீகரிக்க முடியாது.',
            'Bulk order approved.'=>'மொத்த ஆணை அங்கீகரிக்கப்பட்டது.', 'Bulk order rejected.'=>'மொத்த ஆணை நிராகரிக்கப்பட்டது.',
            'Enter a valid receipt quantity.'=>'செல்லுபடியாகும் பெறுதல் அளவை உள்ளிடவும்.',
            'This order is not approved for receipt.'=>'இந்த ஆணை பெறுவதற்காக அங்கீகரிக்கப்படவில்லை.',
            'Invalid workflow action.'=>'செல்லுபடியாகாத பணிப்பாய்வு நடவடிக்கை.',
            'Unable to update workflow.'=>'பணிப்பாய்வைப் புதுப்பிக்க முடியவில்லை.',
            'Bulk workflow unavailable. Run migrations first.'=>'மொத்த பணிப்பாய்வு கிடைக்கவில்லை. முதலில் தரவுத்தள இடம்பெயர்வுகளை இயக்கவும்.',
        ],
    ];

    // Service-division labels explain SSO-only areas without presenting them as official DS Divisions.
    $serviceDivisionTranslations = [
        'si' => [
            'Service Division'=>'සේවා කොට්ඨාසය',
            'Unavailable: active SSO assigned'=>'ලබාගත නොහැක: සක්‍රිය SSO නිලධාරියෙකු පවරා ඇත',
            'This division already has an active Social Service Officer.'=>'මෙම කොට්ඨාසයට දැනටමත් සක්‍රිය සමාජ සේවා නිලධාරියෙකු පවරා ඇත.',
        ],
        'ta' => [
            'Service Division'=>'சேவைப் பிரிவு',
            'Unavailable: active SSO assigned'=>'கிடைக்காது: செயலில் உள்ள SSO நியமிக்கப்பட்டுள்ளார்',
            'This division already has an active Social Service Officer.'=>'இந்தப் பிரிவுக்கு ஏற்கனவே செயலில் உள்ள சமூக சேவை அலுவலர் நியமிக்கப்பட்டுள்ளார்.',
        ],
    ];

    // Service-centre location guidance is separate because GN Divisions do not apply there.
    $serviceDivisionGnTranslations = [
        'si' => [
            'GN Division is not applicable for service divisions.' => 'සේවා කොට්ඨාස සඳහා ග්‍රාම නිලධාරී කොට්ඨාසය අදාළ නොවේ.',
        ],
        'ta' => [
            'GN Division is not applicable for service divisions.' => 'சேவைப் பிரிவுகளுக்கு கிராம அலுவலர் பிரிவு பொருந்தாது.',
        ],
    ];

    // Subject Officer eligibility configuration uses the same terminology on forms and rule tables.
    $eligibilityConfigTranslations = [
        'si' => [
            'Eligibility Configuration'=>'සුදුසුකම් වින්‍යාසය','Eligibility Rule Builder'=>'සුදුසුකම් නීති සාදනය','Build New Eligibility Rule'=>'නව සුදුසුකම් නීතියක් සාදන්න','View Configured Rules'=>'වින්‍යාස කළ නීති බලන්න','Review existing rules or open one to change all of its details.'=>'පවතින නීති සමාලෝචනය කරන්න හෝ සියලු විස්තර වෙනස් කිරීමට නීතියක් විවෘත කරන්න.',
            'Eligibility Rule Configuration'=>'සුදුසුකම් නීති වින්‍යාසය','Build Eligibility Rule'=>'සුදුසුකම් නීතිය සාදන්න','Add a disability type first, then configure its available aid items and probation rules.'=>'පළමුව ආබාධිතභාව වර්ගයක් එක් කර, පසුව එයට ලබාගත හැකි ආධාර අයිතම සහ සීමා කාල නීති සකසන්න.',
            'Close'=>'වසන්න','Confirm changes'=>'වෙනස්කම් තහවුරු කරන්න','You are about to update the eligibility details for'=>'ඔබ පහත අයිතමයේ සුදුසුකම් විස්තර යාවත්කාලීන කිරීමට සූදානම්ය:','Do you want to continue?'=>'ඔබට ඉදිරියට යාමට අවශ්‍යද?','Eligibility rule updated.'=>'සුදුසුකම් නීතිය සාර්ථකව යාවත්කාලීන කරන ලදී.','Disability type saved.'=>'ආබාධිතභාව වර්ගය සුරකින ලදී.','Aid item and probation rule saved.'=>'ආධාර අයිතමය සහ සීමා කාල නීතිය සුරකින ලදී.','Eligibility rule deleted permanently.'=>'සුදුසුකම් නීතිය ස්ථිරවම මකා දමන ලදී.',
            'Build Eligibility Rules'=>'සුදුසුකම් නීති සාදන්න','Add a disability type, then configure the aid items available for it.'=>'ආබාධිතභාව වර්ගයක් එක් කර, එයට ලබාගත හැකි ආධාර අයිතම සකසන්න.','Add the first item now. Other items can be selected from its Edit page later.'=>'පළමු අයිතමය දැන් එක් කරන්න. පසුව එහි සංස්කරණ පිටුවෙන් වෙනත් අයිතම තෝරාගත හැක.','Period'=>'කාලය','Unit'=>'ඒකකය','year(s)'=>'අවුරුදු','month(s)'=>'මාස','Disability & Aid Configuration'=>'ආබාධිතභාවය සහ ආධාර වින්‍යාසය','Add Disability Type'=>'ආබාධිතභාව වර්ගයක් එක් කරන්න','Create a disability before assigning eligible aid items.'=>'සුදුසු ආධාර අයිතම පැවරීමට පෙර ආබාධිතභාව වර්ගයක් සාදන්න.','Disability Type'=>'ආබාධිතභාව වර්ගය','Add Disability'=>'ආබාධිතභාවය එක් කරන්න','Add Aid Item'=>'ආධාර අයිතමයක් එක් කරන්න','Set its probation and optionally prohibit related items.'=>'එහි සීමා කාලය සකසා, අවශ්‍ය නම් අදාළ අයිතම තහනම් කරන්න.','Item Name'=>'අයිතමයේ නම','Variety / Model'=>'ප්‍රභේදය / මාදිලිය','Probation Period'=>'සීමා කාලය','Period Unit'=>'කාල ඒකකය','Months'=>'මාස','Years'=>'අවුරුදු','Also prohibit during this period'=>'මෙම කාලය තුළ මේවාද තහනම් කරන්න','Save Aid Item'=>'ආධාර අයිතමය සුරකින්න','Configured Eligibility Rules'=>'වින්‍යාස කළ සුදුසුකම් නීති','Open a rule to view and change all of its details.'=>'සියලු විස්තර බැලීමට සහ වෙනස් කිරීමට නීතියක් විවෘත කරන්න.','Disability'=>'ආබාධිතභාවය','Probation'=>'සීමා කාලය','Other Prohibited Items'=>'වෙනත් තහනම් අයිතම','No aid eligibility rules configured.'=>'ආධාර සුදුසුකම් නීති වින්‍යාස කර නැත.','Edit'=>'සංස්කරණය','Delete'=>'මකන්න','Update'=>'යාවත්කාලීන කරන්න','None'=>'කිසිවක් නැත','Edit Eligibility Rule'=>'සුදුසුකම් නීතිය සංස්කරණය','Back to Eligibility Rules'=>'සුදුසුකම් නීති වෙත ආපසු','Review and change every detail associated with this eligibility rule.'=>'මෙම සුදුසුකම් නීතියට අදාළ සෑම විස්තරයක්ම සමාලෝචනය කර වෙනස් කරන්න.','Item and Disability'=>'අයිතමය සහ ආබාධිතභාවය','Items Prohibited During Probation'=>'සීමා කාලය තුළ තහනම් අයිතම','The same item is always prohibited automatically. Select any additional items below.'=>'එම අයිතමය සැමවිටම ස්වයංක්‍රීයව තහනම් වේ. අමතර අයිතම පහතින් තෝරන්න.','No other items are available yet.'=>'තවම වෙනත් අයිතම නොමැත.','Cancel'=>'අවලංගු කරන්න','Save Changes'=>'වෙනස්කම් සුරකින්න','Active Rule'=>'සක්‍රිය නීතිය',
        ],
        'ta' => [
            'Eligibility Configuration'=>'தகுதி உள்ளமைவு','Eligibility Rule Builder'=>'தகுதி விதி உருவாக்கி','Build New Eligibility Rule'=>'புதிய தகுதி விதியை உருவாக்கவும்','View Configured Rules'=>'உள்ளமைக்கப்பட்ட விதிகளைப் பார்க்கவும்','Review existing rules or open one to change all of its details.'=>'தற்போதுள்ள விதிகளை மதிப்பாய்வு செய்யவும் அல்லது அதன் அனைத்து விவரங்களையும் மாற்ற ஒரு விதியைத் திறக்கவும்.',
            'Eligibility Rule Configuration'=>'தகுதி விதி உள்ளமைவு','Build Eligibility Rule'=>'தகுதி விதியை உருவாக்கவும்','Add a disability type first, then configure its available aid items and probation rules.'=>'முதலில் மாற்றுத்திறன் வகையைச் சேர்த்து, பின்னர் அதற்குக் கிடைக்கும் உதவிப் பொருட்களையும் தடைக் கால விதிகளையும் உள்ளமைக்கவும்.',
            'Close'=>'மூடு','Confirm changes'=>'மாற்றங்களை உறுதிப்படுத்தவும்','You are about to update the eligibility details for'=>'பின்வரும் பொருளின் தகுதி விவரங்களைப் புதுப்பிக்க உள்ளீர்கள்:','Do you want to continue?'=>'தொடர விரும்புகிறீர்களா?','Eligibility rule updated.'=>'தகுதி விதி வெற்றிகரமாகப் புதுப்பிக்கப்பட்டது.','Disability type saved.'=>'மாற்றுத்திறன் வகை சேமிக்கப்பட்டது.','Aid item and probation rule saved.'=>'உதவிப் பொருளும் தடைக் கால விதியும் சேமிக்கப்பட்டன.','Eligibility rule deleted permanently.'=>'தகுதி விதி நிரந்தரமாக நீக்கப்பட்டது.',
            'Build Eligibility Rules'=>'தகுதி விதிகளை உருவாக்கவும்','Add a disability type, then configure the aid items available for it.'=>'மாற்றுத்திறன் வகையைச் சேர்த்து, அதற்குக் கிடைக்கும் உதவிப் பொருட்களை உள்ளமைக்கவும்.','Add the first item now. Other items can be selected from its Edit page later.'=>'முதல் பொருளை இப்போது சேர்க்கவும். பிற பொருட்களை பின்னர் அதன் திருத்தப் பக்கத்தில் தேர்ந்தெடுக்கலாம்.','Period'=>'காலம்','Unit'=>'அலகு','year(s)'=>'ஆண்டு(கள்)','month(s)'=>'மாதம்(கள்)','Disability & Aid Configuration'=>'மாற்றுத்திறன் மற்றும் உதவி உள்ளமைவு','Add Disability Type'=>'மாற்றுத்திறன் வகையைச் சேர்க்கவும்','Create a disability before assigning eligible aid items.'=>'தகுதியான உதவிப் பொருட்களை ஒதுக்குவதற்கு முன் மாற்றுத்திறன் வகையை உருவாக்கவும்.','Disability Type'=>'மாற்றுத்திறன் வகை','Add Disability'=>'மாற்றுத்திறனைச் சேர்க்கவும்','Add Aid Item'=>'உதவிப் பொருளைச் சேர்க்கவும்','Set its probation and optionally prohibit related items.'=>'அதன் தடைக் காலத்தை அமைத்து, விரும்பினால் தொடர்புடைய பொருட்களைத் தடுக்கவும்.','Item Name'=>'பொருளின் பெயர்','Variety / Model'=>'வகை / மாதிரி','Probation Period'=>'தடைக் காலம்','Period Unit'=>'கால அலகு','Months'=>'மாதங்கள்','Years'=>'ஆண்டுகள்','Also prohibit during this period'=>'இந்தக் காலத்தில் இவற்றையும் தடுக்கவும்','Save Aid Item'=>'உதவிப் பொருளைச் சேமிக்கவும்','Configured Eligibility Rules'=>'உள்ளமைக்கப்பட்ட தகுதி விதிகள்','Open a rule to view and change all of its details.'=>'அனைத்து விவரங்களையும் பார்க்கவும் மாற்றவும் ஒரு விதியைத் திறக்கவும்.','Disability'=>'மாற்றுத்திறன்','Probation'=>'தடைக் காலம்','Other Prohibited Items'=>'பிற தடைசெய்யப்பட்ட பொருட்கள்','No aid eligibility rules configured.'=>'உதவித் தகுதி விதிகள் எதுவும் உள்ளமைக்கப்படவில்லை.','Edit'=>'திருத்து','Delete'=>'நீக்கு','Update'=>'புதுப்பி','None'=>'எதுவுமில்லை','Edit Eligibility Rule'=>'தகுதி விதியைத் திருத்து','Back to Eligibility Rules'=>'தகுதி விதிகளுக்குத் திரும்பு','Review and change every detail associated with this eligibility rule.'=>'இந்தத் தகுதி விதியுடன் தொடர்புடைய ஒவ்வொரு விவரத்தையும் மதிப்பாய்வு செய்து மாற்றவும்.','Item and Disability'=>'பொருளும் மாற்றுத்திறனும்','Items Prohibited During Probation'=>'தடைக் காலத்தில் தடைசெய்யப்பட்ட பொருட்கள்','The same item is always prohibited automatically. Select any additional items below.'=>'அதே பொருள் எப்போதும் தானாகத் தடுக்கப்படும். கூடுதல் பொருட்களை கீழே தேர்ந்தெடுக்கவும்.','No other items are available yet.'=>'வேறு பொருட்கள் இன்னும் கிடைக்கவில்லை.','Cancel'=>'ரத்துசெய்','Save Changes'=>'மாற்றங்களைச் சேமி','Active Rule'=>'செயலில் உள்ள விதி',
        ],
    ];

    $keys = array_unique(array_merge(array_keys(widmsTranslations()[$language] ?? []), $commonKeys, array_keys($socialOfficerTranslations[$language] ?? []), array_keys($stakeholderTranslations[$language] ?? []), array_keys($adminTranslations[$language] ?? []), array_keys($contactLensTranslations[$language] ?? []), array_keys($serviceDivisionTranslations[$language] ?? []), array_keys($serviceDivisionGnTranslations[$language] ?? []), array_keys($eligibilityConfigTranslations[$language] ?? [])));
    $payload = [];
    foreach ($keys as $key) {
        $translated = $eligibilityConfigTranslations[$language][$key] ?? $serviceDivisionGnTranslations[$language][$key] ?? $serviceDivisionTranslations[$language][$key] ?? $contactLensTranslations[$language][$key] ?? $adminTranslations[$language][$key] ?? $stakeholderTranslations[$language][$key] ?? $socialOfficerTranslations[$language][$key] ?? $commonTranslations[$language][$key] ?? t($key);
        if ($translated !== $key) {
            $payload[$key] = $translated;
        }
    }

    // Longest phrases are replaced first so a short label cannot split a full sentence.
    uksort($payload, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    return $payload;
}

/* Return one shared asset block for pages that still contain legacy English markup. */
function widmsUiTranslationAssetsHtml(): string
{
    if (widmsLanguage() === 'en') {
        return '';
    }

    $payload = json_encode(widmsUiTranslationPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return '<script>window.WIDMS_TRANSLATIONS=' . ($payload ?: '{}') . ';</script><script src="assets/js/i18n-ui.js"></script>';
}
