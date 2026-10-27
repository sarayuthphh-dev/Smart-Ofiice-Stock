<?php
declare(strict_types=1);

function interface_palette():array{$key=setting('theme_color','blue');return match($key){'navy'=>['#244b6b','#17354d','#527fa1'],'teal'=>['#0f766e','#0b5752','#2aa69d'],'green'=>['#278052','#1b603c','#4aa979'],'purple'=>['#6d4aaa','#50347f','#9272c6'],default=>['#1769aa','#0d4f86','#39a7e4']};}
function brand_logo(string$class='brand-mark'):string{$path=setting('logo_path','');if($path!=='')return '<img class="'.h($class).' brand-logo" src="branding.php?v='.rawurlencode($path).'" alt="โลโก้ '.h(setting('office_name',cfg('app.office_name'))).'">';return '<span class="'.h($class).'" aria-hidden="true">SOS</span>';}
function ui_icon(string$name,string$class='ui-icon'):string{$paths=[
    'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>','home'=>'<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/>',
    'plus'=>'<path d="M12 5v14M5 12h14"/>','clipboard'=>'<path d="M9 5h6M9 3h6v4H9z"/><path d="M7 5H5v16h14V5h-2M8 12h8M8 16h6"/>',
    'check'=>'<path d="m5 12 4 4L19 6"/>','approval'=>'<path d="M8 4h8M9 2h6v4H9z"/><path d="M7 4H5v17h14V4h-2M8 12l2.5 2.5L16 9"/>',
    'box'=>'<path d="m4 7 8-4 8 4-8 4zM4 7v10l8 4 8-4V7M12 11v10"/>','return'=>'<path d="M9 7 4 12l5 5M5 12h9a5 5 0 0 1 5 5v2"/>',
    'inventory'=>'<path d="M4 7h16v14H4zM3 3h18v4H3zM9 11h6"/>','count'=>'<path d="M5 4h14v17H5zM8 8h8M8 12h8M8 16h5"/>',
    'chart'=>'<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>','image'=>'<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>',
    'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
    'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
    'building'=>'<path d="M3 21h18M6 21V5h12v16M9 9h2M13 9h2M9 13h2M13 13h2M10 21v-4h4v4"/>',
    'logout'=>'<path d="M10 17l5-5-5-5M15 12H3M15 3h5v18h-5"/>','login'=>'<path d="m14 7-5 5 5 5M9 12h12M9 3H4v18h5"/>','alert'=>'<path d="M12 3 2.5 20h19zM12 9v4M12 17h.01"/>','bell'=>'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
    'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','document'=>'<path d="M6 2h9l4 4v16H6zM14 2v5h5M9 12h7M9 16h7"/>','trend'=>'<path d="m3 17 6-6 4 4 8-9M15 6h6v6"/>'];
    $path=$paths[$name]??$paths['document'];return '<svg class="'.h($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';}
function user_role_label(array$roles):string{$labels=['admin'=>'ผู้ดูแลระบบ','supply'=>'เจ้าหน้าที่พัสดุ','approver'=>'ผู้อนุมัติ','requester'=>'ผู้เบิก'];foreach($labels as$code=>$label)if(in_array($code,$roles,true))return $label;return 'ผู้ใช้งาน';}

function render_header(string $title,bool $nav=true): void {
    $u=user();$current=(string)($_GET['page']??'dashboard');
    $adminCss=filemtime(__DIR__.'/../public/vendor/adminlte/adminlte.min.css');
    $appCss=filemtime(__DIR__.'/../public/assets/app.css');$systemName=setting('system_name',cfg('app.name'));$officeName=setting('office_name',cfg('app.office_name'));[$primary,$dark,$accent]=interface_palette(); ?>
<!doctype html><html lang="th" data-bs-theme="light" data-lte-color-mode="off" data-lte-print="plain"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title><?=h($title)?> · <?=h($systemName)?></title><link rel="stylesheet" href="vendor/adminlte/adminlte.min.css?v=<?=$adminCss?>"><link rel="stylesheet" href="assets/app.css?v=<?=$appCss?>"><style>:root{--blue:<?=h($primary)?>;--blue-dark:<?=h($dark)?>;--sky:<?=h($accent)?>;--bs-primary:<?=h($primary)?>;--bs-primary-rgb:<?=h(implode(',',sscanf($primary,'#%02x%02x%02x')))?>}</style></head><body class="<?=$nav&&$u?'layout-fixed sidebar-expand-lg bg-body-tertiary app-shell':'plain-page'?>">
<?php if($nav&&$u): ?><div class="app-wrapper"><nav class="app-header navbar navbar-expand bg-body"><div class="container-fluid"><button class="nav-link sidebar-toggle" type="button" data-lte-toggle="sidebar" aria-label="เปิดหรือปิดเมนู"><?=ui_icon('menu')?></button><div class="header-title"><b><?=h($title)?></b><small><?=h($systemName)?></small></div><div class="ms-auto user-menu"><span class="user-avatar" aria-hidden="true"><?=h(mb_substr($u['full_name'],0,1))?></span><span class="user-copy"><span class="user-name"><?=h($u['full_name'])?></span><small><?=h(user_role_label($u['roles']))?></small></span><a class="logout-link" href="?page=logout" title="ออกจากระบบ"><?=ui_icon('logout')?><span>ออกจากระบบ</span></a></div></div></nav>
<aside class="app-sidebar shadow" data-bs-theme="dark"><div class="sidebar-brand"><a href="index.php" class="brand-link"><?=brand_logo()?><span class="brand-text"><b><?=h($systemName)?></b><small><?=h($officeName)?></small></span></a></div><div class="sidebar-wrapper"><nav class="mt-2"><ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false"><?php render_sidebar_menu($current);?></ul></nav></div></aside><?php endif;
}

function menu_catalog():array{return[
    'dashboard'=>['label'=>'ภาพรวม','icon'=>'home','group'=>'ภาพรวมระบบ','roles'=>[],'order'=>10],
    'request_form'=>['label'=>'เบิกวัสดุ','icon'=>'plus','group'=>'งานเบิกวัสดุ','roles'=>['requester'],'order'=>20],
    'my_requests'=>['label'=>'คำขอของฉัน','icon'=>'clipboard','group'=>'งานเบิกวัสดุ','roles'=>['requester'],'order'=>30],
    'confirm_receipts'=>['label'=>'ยืนยันรับของ','icon'=>'check','group'=>'งานเบิกวัสดุ','roles'=>['requester'],'order'=>40],
    'line_connect'=>['label'=>'เชื่อมต่อ LINE','icon'=>'bell','group'=>'งานเบิกวัสดุ','roles'=>['requester'],'order'=>45],
    'approvals'=>['label'=>'อนุมัติคำขอ','icon'=>'approval','group'=>'งานอนุมัติ','roles'=>['approver'],'order'=>50],
    'issues'=>['label'=>'จ่ายวัสดุ','icon'=>'box','group'=>'งานพัสดุ','roles'=>['supply'],'order'=>60],
    'returns'=>['label'=>'คืนวัสดุ','icon'=>'return','group'=>'งานพัสดุ','roles'=>['supply'],'order'=>70],
    'materials'=>['label'=>'คลังวัสดุ','icon'=>'inventory','group'=>'งานพัสดุ','roles'=>['supply'],'order'=>80],
    'counts'=>['label'=>'ตรวจนับ','icon'=>'count','group'=>'งานพัสดุ','roles'=>['supply'],'order'=>90],
    'reports'=>['label'=>'รายงาน','icon'=>'chart','group'=>'ข้อมูลและรายงาน','roles'=>['supply','approver','admin'],'order'=>100],
    'stock_integrity'=>['label'=>'ตรวจสอบความถูกต้องสต๊อก','icon'=>'check','group'=>'ข้อมูลและรายงาน','roles'=>['supply','admin'],'order'=>105],
    'admin_branding'=>['label'=>'ชื่อและโลโก้','icon'=>'image','group'=>'ตั้งค่าระบบ','roles'=>['admin'],'order'=>110],
    'admin_navigation_reports'=>['label'=>'เมนูและรายงาน','icon'=>'settings','group'=>'ตั้งค่าระบบ','roles'=>['admin'],'order'=>120],
    'admin_requisition_print'=>['label'=>'รูปแบบใบเบิก','icon'=>'document','group'=>'ตั้งค่าระบบ','roles'=>['admin'],'order'=>130],
    'admin_users'=>['label'=>'บัญชีผู้ใช้','icon'=>'users','group'=>'ตั้งค่าระบบ','roles'=>['admin'],'order'=>140],
    'admin_groups'=>['label'=>'กลุ่มงาน','icon'=>'building','group'=>'ตั้งค่าระบบ','roles'=>['admin'],'order'=>150],
    'admin_line'=>['label'=>'แจ้งเตือน LINE','icon'=>'bell','group'=>'ตั้งค่าระบบ','roles'=>['admin'],'order'=>160],
];}
function render_sidebar_menu(string$current):void{$items=[];$groups=array_flip(['ภาพรวมระบบ','งานเบิกวัสดุ','งานอนุมัติ','งานพัสดุ','ข้อมูลและรายงาน','ตั้งค่าระบบ']);foreach(menu_catalog()as$page=>$item){if($item['roles']&&!has_role(...$item['roles']))continue;if(!in_array($page,['dashboard','admin_navigation_reports'],true)&&setting('menu_enabled_'.$page,'1')==='0')continue;$item['page']=$page;$item['label']=setting('menu_label_'.$page,$item['label']);$item['sort']=(int)setting('menu_order_'.$page,(string)$item['order']);$item['group_sort']=$groups[$item['group']]??99;$items[]=$item;}usort($items,fn($a,$b)=>$a['group_sort']<=>$b['group_sort']?:($a['sort']<=>$b['sort']?:$a['order']<=>$b['order']));$last='';$receiptCount=has_role('requester')?pending_receipt_count((int)user()['id']):0;foreach($items as$item){if($item['group']!==$last){echo'<li class="nav-header">'.h($item['group']).'</li>';$last=$item['group'];}echo nav_item($item['page'],$item['label'],$item['icon'],$current,$item['page']==='confirm_receipts'?$receiptCount:0);}}
function active_page(string$current):string{return match($current){'request_view'=>'my_requests','material_edit','material_import'=>'materials','count_view'=>'counts','user_edit'=>'admin_users','admin'=>'admin_branding',default=>$current};}
function nav_item(string$page,string$label,string$icon,string$current,int$count=0):string{$active=active_page($current)===$page;return '<li class="nav-item"><a href="?page='.h($page).'" class="nav-link'.($active?' active':'').'"'.($active?' aria-current="page"':'').'><span class="nav-icon menu-symbol">'.ui_icon($icon).'</span><p>'.h($label).'</p>'.($count>0?'<span class="menu-count" title="รายการรอยืนยัน">'.h($count).'</span>':'').'<span class="nav-active-dot" aria-hidden="true"></span></a></li>';}
function render_footer(): void {$adminJs=filemtime(__DIR__.'/../public/vendor/adminlte/adminlte.min.js');$appJs=filemtime(__DIR__.'/../public/assets/app.js');?><script src="vendor/adminlte/adminlte.min.js?v=<?=$adminJs?>"></script><script src="assets/app.js?v=<?=$appJs?>"></script></body></html><?php }
function render_flashes():void{foreach(take_flashes()as$f):?><div class="alert <?=h($f['type'])?>" role="alert"><?=h($f['message'])?></div><?php endforeach;}
function page_start(string$title,string$subtitle=''):void{render_header($title);?><main class="app-main"><div class="app-content-header"><div class="content-container"><div class="page-head"><div class="page-title-block"><span class="page-eyebrow">SMART OFFICE STOCK</span><h1><?=h($title)?></h1><?php if($subtitle):?><p><?=h($subtitle)?></p><?php endif;?></div><ol class="breadcrumb"><li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li><li class="breadcrumb-item active" aria-current="page"><?=h($title)?></li></ol></div></div></div><div class="app-content"><div class="content-container"><?php render_flashes();}
function page_end():void{?></div></div></main><footer class="app-footer"><span><?=h(setting('office_name',cfg('app.office_name')))?></span><small>Smart Office Stock</small></footer></div><?php render_footer();}
function csrf_field():void{?><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>"><?php }
function badge(string$status):string{return '<span class="badge s-'.h($status).'">'.h(status_th($status)).'</span>';}
function request_badge(array$request):string{$completed=!empty($request['completed_at']);$status=$completed?'completed':(string)$request['status'];return badge($status);}
function not_found():never{http_response_code(404);page_start('ไม่พบข้อมูล');echo'<section class="card"><p>ไม่พบรายการที่ต้องการ</p></section>';page_end();exit;}
