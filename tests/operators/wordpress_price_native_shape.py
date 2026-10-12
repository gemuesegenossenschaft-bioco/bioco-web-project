"""Explicit read-only gate against the installed staging WordPress block parser.

Run: BIOCO_PRICE_QA_PLAN=~/.cache/bioco-content-editor/private-price-invoice-qa-plan-v7-20261013.json \
  python3 -m pytest -q tests/operators/wordpress_price_native_shape.py

The operator must also inspect the exact candidate SHA, owned draft state, and browser.
No SQL updates, media uploads, or WordPress option writes occur here.
"""
import json
import os
import shlex
import subprocess
from pathlib import Path


ROOT = Path(__file__).parents[2]
SOURCE = ROOT / 'wordpress/web/app/mu-plugins/bioco-import/includes/price-adoption.php'
HOST = os.environ.get('BIOCO_PRICE_WP_HOST', 'bioco@193.33.128.160')
WP = os.environ.get('BIOCO_PRICE_WP_CLI',
    '/home/bioco/bin/wp --path=/home/bioco/staging.bioco.ch --user=7')


def installed_eval(code: str, payload: str) -> dict | list:
    command = ['ssh', '-T', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=12', HOST,
               f'{WP} eval {shlex.quote(code)}']
    result = subprocess.run(command, input=payload, text=True, capture_output=True, timeout=45, check=True)
    assert result.stderr == '', result.stderr
    return json.loads(result.stdout.strip().splitlines()[-1])


def candidate_source() -> str:
    source = SOURCE.read_text()
    source = source.removeprefix('<?php').replace("require_once __DIR__ . '/price-layouts.php';", '')
    source = source.split("if (defined('WP_CLI') && WP_CLI) {")[0]
    return source.replace('bioco_price_adoption_', 'bioco_candidate_price_adoption_')


def test_live_legacy_calculator_converts_with_installed_parser():
    code = r'''
    eval(file_get_contents('php://stdin'));
    $p=get_post(10);
    if (!$p || $p->post_type!=='page' || $p->post_status!=='publish') throw new RuntimeException('Wrong legacy source.');
    $source=['ID'=>10,'post_type'=>$p->post_type,'post_status'=>$p->post_status,
        'post_title'=>$p->post_title,'post_content'=>$p->post_content,'post_modified_gmt'=>$p->post_modified_gmt];
    $plan=bioco_candidate_price_adoption_plan($source,'calculator',home_url('/'),hash('sha256',$p->post_content));
    $b=parse_blocks($plan['after_content']); $sections=$b[0]['innerBlocks']??[];
    $calc=$sections[1]['innerBlocks']??[];
    $class=static fn($x)=>$x['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class']??'';
    $head=$calc[2]['innerBlocks']??[]; $abo=$calc[3]['innerBlocks']??[]; $shares=$calc[4]['innerBlocks']??[];
    $amount=$abo[2]??[];
    $phone=array_map(static fn($c)=>$c['attrs']['module']['decoration']['sizing']['phone']['value']['gridColumnEnd']??null,$head);
    $headingFonts=array_map(static fn($c)=>$c['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']??[],$head);
    $bad=$plan['after_content']; $at=strpos($bad,'bioco-price-invoice-shares');
    if ($at===false) throw new RuntimeException('Shares row missing.');
    $end=strpos($bad,'<!-- /wp:divi/row -->',$at);
    preg_match_all('/<!-- wp:divi\/column(?: \{[^\n]*?\})? \/?-->/',substr($bad,$at,$end-$at),$matches,PREG_OFFSET_CAPTURE);
    if (count($matches[0])!==5) throw new RuntimeException('Shares cell count changed.');
    $fifth=$matches[0][4]; $start=$at+$fifth[1]; $tag=$fifth[0];
    if (str_ends_with($tag,' /-->')) throw new RuntimeException('Generator produced nonempty self-closed shares cell.');
    $bad=substr_replace($bad,substr($tag,0,-3).' /-->',$start,strlen($tag));
    $malformed=parse_blocks($bad);
    echo wp_json_encode(['source_sha'=>hash('sha256',$p->post_content),'top'=>count($sections),
        'rows'=>count($calc),'head'=>$class($calc[2]),'abo'=>$class($calc[3]),
        'total'=>$class($calc[5]),'head_phone'=>$phone,'heading_fonts'=>$headingFonts,
        'amount_layout'=>$amount['attrs']['module']['decoration']['layout']??[],
        'amount_prefix_margin'=>$amount['innerBlocks'][0]['attrs']['module']['decoration']['spacing']['desktop']['value']['margin']['right']??null,
        'article_layout'=>$abo[0]['attrs']['module']['decoration']['layout']??[],
        'shares_layout'=>$shares[0]['attrs']['module']['decoration']['layout']??[],
        'article_text_fonts'=>array_map(static fn($m)=>$m['attrs']['content']['decoration']['bodyFont']['body']['font']??[],$abo[0]['innerBlocks']??[]),
        'shares_text_fonts'=>array_map(static fn($m)=>$m['attrs']['content']['decoration']['bodyFont']['body']['font']??[],$shares[0]['innerBlocks']??[]),
        'total_heading_font'=>$calc[5]['innerBlocks'][0]['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']??[],
        'action_layout'=>$shares[4]['attrs']['module']['decoration']['layout']??[],
        'action_label_font'=>$shares[4]['innerBlocks'][0]['attrs']['content']['decoration']['bodyFont']['body']['font']??[],
        'action_button_font'=>$shares[4]['innerBlocks'][1]['attrs']['button']['decoration']['font']['font']??[],
        'action_button_padding'=>$shares[4]['innerBlocks'][1]['attrs']['module']['decoration']['spacing']['desktop']['value']['padding']??[],
        'head_spare'=>count($head[4]['innerBlocks']??[]),'abo_spare'=>count($abo[4]['innerBlocks']??[]),
        'shares_last'=>count($shares[4]['innerBlocks']??[]),
        'total_value_inside'=>str_contains(wp_json_encode($calc),'bioco-price-value-total'),
        'cta_inside'=>str_contains(wp_json_encode($calc),'bioco-price-signup'),
        'roundtrip_top'=>count(parse_blocks(serialize_blocks($b))[0]['innerBlocks']??[]),
        'malformed_top'=>count($malformed[0]['innerBlocks']??[])],JSON_UNESCAPED_SLASHES),PHP_EOL;
    '''
    result = installed_eval(code, candidate_source())
    assert result['source_sha'] == 'b02c4e235128e159438277ac2601e5944cb0d7c64af521fd84fa9f478fec39e2'
    assert (result['top'], result['rows'], result['roundtrip_top']) == (2, 8, 2)
    assert result['malformed_top'] != 2  # The V2 regression must be detected.
    assert (result['head'], result['abo'], result['total']) == (
        'bioco-price-invoice-head', 'bioco-price-invoice-abo', 'bioco-price-invoice-total')
    assert result['head_phone'] == ['2', '3', '2', '3', None]
    assert all(font['desktop']['value'] == {'headingLevel': 'h3', 'size': '16px',
        'lineHeight': '1.6em', 'weight': '500', 'color': '#ffffff'}
        and font['phone']['value'] == {'size': '14px', 'lineHeight': '1.6em'}
        for font in result['heading_fonts'][:4])
    assert result['amount_prefix_margin'] == '4px'
    assert all(result['amount_layout'][device]['value']['flexDirection'] == 'row'
        and result['amount_layout'][device]['value']['columnGap'] == '0px'
        for device in ('desktop', 'tablet', 'phone'))
    assert all(result['article_layout'][device]['value']['flexWrap'] == 'wrap'
        and result['article_layout'][device]['value']['rowGap'] == '4px'
        and result['shares_layout'][device]['value']['rowGap'] == '4px'
        for device in ('desktop', 'tablet', 'phone'))
    assert [font['desktop']['value']['size'] for font in result['article_text_fonts']] == ['16px'] + ['14px'] * 4
    assert [font['desktop']['value']['size'] for font in result['shares_text_fonts']] == ['16px','14px','14px']
    assert result['total_heading_font']['desktop']['value']['size'] == '16px'
    assert result['total_heading_font']['phone']['value']['size'] == '14px'
    assert all(result['action_layout'][device]['value']['rowGap'] == '4px' for device in ('desktop','tablet','phone'))
    assert result['action_label_font']['desktop']['value']['size'] == '12px'
    assert result['action_button_font']['desktop']['value']['size'] == '14px'
    assert result['action_button_padding'] == {'top':'11px','bottom':'11px','left':'16px','right':'16px'}
    assert (result['head_spare'], result['abo_spare'], result['shares_last']) == (0, 0, 4)
    assert result['total_value_inside'] and result['cta_inside']


def test_private_qa_patch_keeps_installed_block_tree_balanced():
    path = Path(os.environ.get('BIOCO_PRICE_QA_PLAN',
        '~/.cache/bioco-content-editor/private-price-invoice-qa-plan-v7-20261013.json')).expanduser()
    assert path.is_file(), f'Private QA plan missing: {path}'
    code = r'''
    $plan=json_decode(file_get_contents('php://stdin'),true,512,JSON_THROW_ON_ERROR);$out=[];
    foreach($plan['pages'] as $p){
      $before=parse_blocks($p['before_content']);$after=parse_blocks($p['after_content']);
      $bc=$before[0]['innerBlocks'][4]['innerBlocks']??[];$ac=$after[0]['innerBlocks'][4]['innerBlocks']??[];
      $out[]=['id'=>$p['id'],'before_top'=>count($before[0]['innerBlocks']??[]),
        'after_top'=>count($after[0]['innerBlocks']??[]),'before_rows'=>count($bc),
        'after_rows'=>count($ac),'head_spare'=>count($ac[2]['innerBlocks'][4]['innerBlocks']??[]),
        'abo_spare'=>count($ac[3]['innerBlocks'][4]['innerBlocks']??[]),
        'shares_last'=>count($ac[4]['innerBlocks'][4]['innerBlocks']??[]),
        'total_inside'=>($ac[5]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class']??'')==='bioco-price-invoice-total',
        'cta_inside'=>str_contains(wp_json_encode($ac),'bioco-price-signup'),
        'head_fonts'=>array_map(static fn($c)=>$c['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']??[],array_slice($ac[2]['innerBlocks'],0,4)),
        'phone_padding'=>array_map(static fn($r)=>array_map(static fn($c)=>$c['attrs']['module']['decoration']['spacing']['phone']['value']['padding']??null,$r['innerBlocks']),array_slice($ac,2,4)),
        'amount_layouts'=>array_map(static fn($pair)=>$ac[$pair[0]]['innerBlocks'][$pair[1]]['attrs']['module']['decoration']['layout']??[],[[3,2],[3,3],[4,3],[5,3]]),
        'amount_prefix_margins'=>array_map(static fn($pair)=>$ac[$pair[0]]['innerBlocks'][$pair[1]]['innerBlocks'][0]['attrs']['module']['decoration']['spacing']['desktop']['value']['margin']['right']??null,[[3,2],[3,3],[4,3],[5,3]]),
        'article_layout'=>$ac[3]['innerBlocks'][0]['attrs']['module']['decoration']['layout']??[],
        'shares_layout'=>$ac[4]['innerBlocks'][0]['attrs']['module']['decoration']['layout']??[],
        'total_heading_font'=>$ac[5]['innerBlocks'][0]['innerBlocks'][0]['attrs']['title']['decoration']['font']['font']??[],
        'action_layout'=>$ac[4]['innerBlocks'][4]['attrs']['module']['decoration']['layout']??[],
        'action_button_padding'=>$ac[4]['innerBlocks'][4]['innerBlocks'][1]['attrs']['module']['decoration']['spacing']['desktop']['value']['padding']??[],
        'roundtrip_top'=>count(parse_blocks(serialize_blocks($after))[0]['innerBlocks']??[])];
    }
    echo wp_json_encode($out),PHP_EOL;
    '''
    result = installed_eval(code, path.read_text())
    assert [row['id'] for row in result] == [494, 495]
    for row in result:
        assert (row['before_top'], row['after_top'], row['roundtrip_top']) == (5, 5, 5)
        assert (row['before_rows'], row['after_rows']) == (8, 8)
        assert (row['head_spare'], row['abo_spare'], row['shares_last']) == (0, 0, 4)
        assert row['total_inside'] and row['cta_inside']
        assert all(font['desktop']['value']['size'] == '16px'
            and font['phone']['value']['size'] == '14px' for font in row['head_fonts'])
        assert all(pad == {'top': '8px', 'bottom': '8px', 'left': '16px', 'right': '16px'}
            for invoice in row['phone_padding'] for pad in invoice)
        assert row['amount_prefix_margins'] == ['4px'] * 4
        assert all(layout[device]['value']['flexDirection'] == 'row'
            and layout[device]['value']['columnGap'] == '0px'
            for layout in row['amount_layouts'] for device in ('desktop', 'tablet', 'phone'))
        assert all(row['article_layout'][device]['value']['flexWrap'] == 'wrap'
            and row['article_layout'][device]['value']['rowGap'] == '4px'
            and row['shares_layout'][device]['value']['rowGap'] == '4px'
            for device in ('desktop', 'tablet', 'phone'))
        assert row['total_heading_font']['desktop']['value']['size'] == '16px'
        assert row['total_heading_font']['phone']['value']['size'] == '14px'
        assert all(row['action_layout'][device]['value']['rowGap'] == '4px' for device in ('desktop','tablet','phone'))
        assert row['action_button_padding'] == {'top':'11px','bottom':'11px','left':'16px','right':'16px'}
