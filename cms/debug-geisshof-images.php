<?php
namespace ProcessWire;
header('Content-Type: application/json; charset=utf-8');
$out = [];
$page = $pages->get('/content/wir/');
if(!$page->id) $page = $pages->get('name=wir');
if(!$page->id) {
  echo json_encode(['success'=>false,'error'=>'wir page not found']);
  return;
}
$out['pageId'] = $page->id;
$out['pagePath'] = $page->path;
$out['sections'] = [];
if($page->hasField('content_sections') && $page->content_sections) {
  foreach($page->content_sections as $section) {
    $sid = (string)$section->get('section_id');
    if($sid !== 'geisshof') continue;
    $row = [
      'sectionPageId' => $section->id,
      'section_id' => $sid,
      'fields' => [],
    ];
    foreach(['section_image','section_images','image','images'] as $fname) {
      if(!$section->hasField($fname)) continue;
      $v = $section->get($fname);
      $count = 0;
      $names = [];
      if($v instanceof Pageimages || $v instanceof Pagefiles) {
        $count = $v->count();
        foreach($v as $f) $names[] = $f->name;
      } elseif($v instanceof Pageimage || $v instanceof Pagefile) {
        $count = 1;
        $names[] = $v->name;
      }
      $row['fields'][$fname] = ['count'=>$count,'names'=>$names];
    }
    $out['sections'][] = $row;
  }
}
echo json_encode(['success'=>true,'data'=>$out], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
