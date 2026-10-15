"""Opt-in native Image metadata projection and fresh library resolution.

Unit tests exercise the actual PHP callbacks and registered editor hooks.
Installed renderer and save/reopen evidence remains a separate operator gate.
"""
import copy
import json
import subprocess
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / "wordpress/web/app/mu-plugins/bioco-core"
PHP = CORE / "includes/media-bindings.php"
JS = CORE / "assets/divi-media-bindings.js"


def php(code, payload=None):
    prelude = r'''
    define('ABSPATH','/');
    function add_filter(...$a) {$GLOBALS['filters'][]=$a;}
    function add_action(...$a) {$GLOBALS['actions'][]=$a;}
    function wp_json_encode($a,$flags=0) {return json_encode($a,$flags);}
    function __($text,$domain) {return $text;}
    require $argv[1];
    $request=json_decode(stream_get_contents(STDIN),true);
    '''
    result = subprocess.run(["php", "-r", prelude + code, str(PHP)],
                            input=json.dumps(payload), capture_output=True, text=True, timeout=20)
    assert result.returncode == 0, result.stderr
    assert not result.stderr, result.stderr
    return json.loads(result.stdout)


def javascript(attrs, name="divi/image", mode="attrs"):
    result = subprocess.run(["node", str(ROOT / "tests/fixtures/divi-media-bindings.cjs"), str(JS)],
                            input=json.dumps({"attrs": attrs, "name": name, "mode": mode}),
                            capture_output=True, text=True, timeout=20)
    assert result.returncode == 0, result.stderr
    return json.loads(result.stdout)


def image(sources=None):
    rows = [
        {"id": "alt", "name": "alt", "value": "Old113 alt", "targetElement": "image"},
        {"id": "title", "name": "title", "value": "Old113 title", "targetElement": "image"},
        {"id": "aria", "name": "aria-describedby", "value": "caption", "targetElement": "image"},
        {"id": "wrapper", "name": "title", "value": "Keep wrapper", "targetElement": "main"},
    ]
    attrs = {"image": {"innerContent": {"desktop": {"value": {
        "src": "https://example.test/113.jpg", "id": "113", "alt": "Old113 alt", "titleText": "Old113 title",
        "linkUrl": "/unchanged", "width": "2560", "height": "1280",
    }}}, "decoration": {"fit": {"phone": {"value": {"objectFit": "cover"}}}}},
        "module": {"decoration": {"attributes": {"attributes": copy.deepcopy(rows),
            "desktop": {"value": {"attributes": rows}}}}, "meta": {"adminLabel": {"desktop": {"value": "Keep"}}}},
        "modulePreset": ["authored-preset"]}
    if sources is not None:
        attrs["image"]["advanced"] = {"biocoMetadata": {"desktop": {"value": sources}}}
    return attrs


def project(attrs, name="divi/image"):
    saved = copy.deepcopy(attrs)
    backend = php("$before=$request['attrs'];$v=bioco_media_binding_attrs($before,['name'=>$request['name']]);"
                  "echo json_encode(['value'=>$v,'unchanged'=>$before===$request['attrs']]);", {"attrs": attrs, "name": name})
    editor = javascript(attrs, name)
    assert backend["value"] == editor["value"]
    assert backend["unchanged"] and editor["unchanged"] and attrs == saved
    return backend["value"]


def token(value):
    assert value.startswith("$variable(") and value.endswith(")$")
    return json.loads(value[len("$variable("):-2])["value"]


@pytest.mark.parametrize("sources", [None, [], {"altSource": "module", "titleSource": "module"}, {"altSource": "unknown"}])
def test_existing_contextual_decorative_and_unknown_choices_are_unchanged(sources):
    attrs = image(sources)
    attrs["module"]["decoration"]["attributes"]["desktop"]["value"]["attributes"][0]["value"] = ""
    assert project(attrs) == attrs
    assert project(image({"altSource": "library"}), "divi/blurb") == image({"altSource": "library"})


def test_library_projection_removes_only_opted_in_image_rows_without_changing_saved_controls():
    attrs = image({"altSource": "library"})
    after = project(attrs)
    assert token(after["image"]["innerContent"]["desktop"]["value"]["alt"])["name"] == "bioco_media_alt"
    assert after["image"]["innerContent"]["desktop"]["value"]["titleText"] == "Old113 title"
    for rows in [after["module"]["decoration"]["attributes"]["attributes"],
                 after["module"]["decoration"]["attributes"]["desktop"]["value"]["attributes"]]:
        assert [r["id"] for r in rows] == ["title", "aria", "wrapper"]
    assert after["image"]["decoration"] == attrs["image"]["decoration"]
    assert after["modulePreset"] == attrs["modulePreset"]
    assert project(after) == after


def test_replacement_to_114_with_blank_alt_cannot_keep_113_literal_or_binding():
    attrs = image({"altSource": "library", "titleSource": "library"})
    attrs["image"]["innerContent"]["desktop"]["value"].update(id="114", src="https://example.test/114.jpg", alt="")
    after = project(attrs)
    value = after["image"]["innerContent"]["desktop"]["value"]
    for field in ["alt", "titleText"]:
        assert json.loads(token(value[field])["settings"]["image"])["id"] == "114"
        assert "113" not in value[field]
    assert all(row.get("targetElement") != "image" or row["name"] not in ["alt", "title"]
               for row in after["module"]["decoration"]["attributes"]["desktop"]["value"]["attributes"])


def test_native_image_breakpoints_and_states_keep_matching_identity_and_native_inheritance():
    attrs = image({"altSource": "library", "titleSource": "library"})
    attrs["image"]["innerContent"].update({
        "tablet": {"value": {"id": 114, "src": "https://example.test/114.jpg", "alt": "stale"}},
        "phone": {"value": {"width": "320", "alt": "stale"}},
    })
    attrs["image"]["innerContent"]["desktop"]["hover"] = {"id": 115, "src": "https://example.test/115.jpg"}
    after = project(attrs)["image"]["innerContent"]
    assert json.loads(token(after["tablet"]["value"]["alt"])["settings"]["image"])["id"] == "114"
    assert after["phone"]["value"] == {"width": "320"}
    assert json.loads(token(after["desktop"]["hover"]["alt"])["settings"]["image"])["id"] == "115"
    attrs["image"]["innerContent"]["phone"]["value"]["src"] = "https://example.test/external.jpg"
    after = project(attrs)["image"]["innerContent"]
    assert json.loads(token(after["phone"]["value"]["alt"])["settings"]["image"])["id"] == "0"


@pytest.mark.parametrize("sources", [{"altSource": "decorative"}, {"titleSource": "none"}])
def test_explicit_empty_modes_bind_empty_instead_of_library_fallback(sources):
    value = project(image(sources))["image"]["innerContent"]["desktop"]["value"]
    field = "alt" if "altSource" in sources else "titleText"
    assert json.loads(token(value[field])["settings"]["image"])["id"] == "0"


LIBRARY = r'''
function get_post($id) {return isset($GLOBALS['posts'][$id])?(object)$GLOBALS['posts'][$id]:null;}
function get_post_meta($id,$key,$single) {return $GLOBALS['alts'][$id]??'';}
function wp_attachment_is_image($id) {return ($GLOBALS['posts'][$id]['image']??false);}
function current_user_can(...$args) {return $GLOBALS['allowed']??false;}
function get_post_status($id) {return $GLOBALS['posts'][$id]['post_status']??false;}
function post_password_required($id) {return $GLOBALS['posts'][$id]['password']??false;}
$GLOBALS['posts']=$request['posts'];$GLOBALS['alts']=$request['alts'];
'''


def library_payload():
    return {"posts": {"113": {"post_type": "attachment", "post_status": "inherit", "post_parent": 0,
                            "post_title": 'Title "113" & field', "image": True},
                      "114": {"post_type": "attachment", "post_status": "inherit", "post_parent": 0,
                            "post_title": "Title114", "image": True}}, "alts": {"113": 'Alt "113" & field', "114": ""}}


def test_metadata_edits_resolve_fresh_without_reselect_and_blank_is_authoritative():
    code = LIBRARY + r'''
    $context=['name'=>'bioco_media_alt','settings'=>['image'=>json_encode(['id'=>'113'])]];
    $initial=bioco_media_binding_value('fallback',$context);
    $GLOBALS['alts'][113]='Changed library alt';$GLOBALS['posts'][113]['post_title']='Changed library title';
    $updated=bioco_media_binding_value('fallback',$context);
    $context['name']='bioco_media_title';$title=bioco_media_binding_value('fallback',$context);
    $context['name']='bioco_media_alt';$context['settings']['image']=json_encode(['id'=>'114']);
    echo json_encode([$initial,$updated,$title,bioco_media_binding_value('stale113',$context),
      bioco_media_binding_value('unrelated',['name'=>'other'])]);
    '''
    assert php(code, library_payload()) == ['Alt "113" & field', "Changed library alt", "Changed library title", "", "unrelated"]


@pytest.mark.parametrize("identity", [None, 0, -1, 1.5, [], {}, "113x", "01", "1e2"])
def test_invalid_identities_fail_empty(identity):
    payload = library_payload()
    payload["identity"] = identity
    assert php(LIBRARY + "echo json_encode(bioco_media_binding_value('stale',['name'=>'bioco_media_alt',"
               "'settings'=>['image'=>json_encode(['id'=>$request['identity']])]]));", payload) == ""


@pytest.mark.parametrize("changed", [{"post_type": "page"}, {"image": False}, {"post_status": "private"},
                                    {"post_parent": 114}])
def test_nonimages_and_unreadable_private_parent_metadata_are_not_exposed(changed):
    payload = library_payload()
    payload["posts"]["113"].update(changed)
    payload["posts"]["114"]["post_status"] = "private"
    assert php(LIBRARY + "echo json_encode(bioco_media_binding_value('stale',['name'=>'bioco_media_alt',"
               "'settings'=>['image'=>'{\"id\":\"113\"}']]));", payload) == ""


def test_controls_extend_supported_native_content_schema_without_mutating_metadata():
    metadata = {"image": {"type": "object", "settings": {"advanced": {"lightbox": {"old": "kept"}}}}}
    result = javascript(metadata, mode="controls")
    assert result["unchanged"]
    advanced = result["value"]["image"]["settings"]["advanced"]
    assert advanced["lightbox"] == {"old": "kept"}
    for field in advanced["biocoMetadata"]["items"].values():
        assert field["groupSlug"] == "contentImage"
        assert field["attrName"] == "image.advanced.biocoMetadata"
        assert field["component"]["name"] == "divi/select"
        assert field["defaultAttr"]["desktop"]["value"] == {"altSource": "module", "titleSource": "module"}
        assert field["features"]["responsive"] is False
    options = php("echo json_encode(bioco_media_binding_fields(['unrelated'=>['kept'=>true]]));")
    assert options["unrelated"] == {"kept": True}
    assert options["bioco_media_alt"]["fields"]["image"]["type"] == "upload"


def test_callbacks_are_registered_for_native_attrs_dynamic_resolution_and_builder_load():
    result = php("echo json_encode(['filters'=>$GLOBALS['filters'],'actions'=>$GLOBALS['actions']]);")
    assert [hook[0] for hook in result["filters"]] == ["divi_module_dynamic_content_options",
        "divi_module_dynamic_content_resolved_value", "divi_module_library_register_module_attrs"]
    assert result["actions"][0][0] == "divi_visual_builder_assets_after_enqueue_app_window_scripts"


def test_password_parent_reader_without_password_is_denied_while_editor_is_allowed():
    payload = library_payload()
    payload["posts"]["113"]["post_parent"] = 114
    payload["posts"]["114"].update(post_status="publish", password=True)
    code = LIBRARY.replace(
        "function current_user_can(...$args) {return $GLOBALS['allowed']??false;}",
        "function current_user_can($cap,$id) {$GLOBALS['cap_checks'][]=[$cap,$id];"
        "return $cap==='read_post'||($GLOBALS['canEditParent']??false);}",
    ) + r'''
    $context=['name'=>'bioco_media_alt','settings'=>['image'=>'{"id":"113"}']];
    $reader=bioco_media_binding_value('stale',$context);
    $GLOBALS['canEditParent']=true;$editor=bioco_media_binding_value('stale',$context);
    $GLOBALS['canEditParent']=false;$GLOBALS['posts'][114]['password']=false;
    $withPassword=bioco_media_binding_value('stale',$context);
    echo json_encode([$reader,$editor,$withPassword,$GLOBALS['cap_checks']]);
    '''
    assert php(code, payload) == ["", 'Alt "113" & field', 'Alt "113" & field',
                                 [["edit_post", 114], ["edit_post", 114]]]


def test_builder_assets_precede_native_registration_and_skip_unauthorized_or_late_loads():
    code = r'''
    class TestConditions {public static function is_vb_app_window(){return $GLOBALS['app'];}}
    class TestRoles {public static function can_current_user_use_visual_builder(){return $GLOBALS['role'];}}
    class_alias('TestConditions','ET\\Builder\\Framework\\Utility\\Conditions');
    class_alias('TestRoles','ET\\Builder\\Framework\\UserRole\\UserRole');
    function is_user_logged_in(){return $GLOBALS['login'];}
    function wp_script_is($handle,$status){return $GLOBALS[$status];}
    function wp_add_inline_script(...$args){$GLOBALS['scripts'][]=$args;return true;}
    $GLOBALS['scripts']=[];$app=$role=$login=$registered=true;$done=false;
    bioco_media_binding_enqueue();
    $good=$GLOBALS['scripts'];$GLOBALS['scripts']=[];
    foreach(['app','role','login','registered','done']as$key){
      $previous=$GLOBALS[$key];$GLOBALS[$key]=!$previous;bioco_media_binding_enqueue();$GLOBALS[$key]=$previous;
    }
    echo json_encode(['good'=>$good,'blocked'=>$GLOBALS['scripts']]);
    '''
    result = php(code)
    assert len(result["good"]) == 1
    assert result["good"][0][0] == "divi-module-library"
    assert result["good"][0][2] == "before"
    assert result["good"][0][1] == JS.read_text()
    assert result["blocked"] == []


def test_dynamic_upload_settings_reject_unparseable_input_and_allow_authorized_private_images():
    payload = library_payload()
    payload["posts"]["113"]["post_status"] = "private"
    result = php(LIBRARY + r'''
    $context=['name'=>'bioco_media_alt','settings'=>['image'=>'{"id":"113"}']];
    $denied=bioco_media_binding_value('stale',$context);$GLOBALS['allowed']=true;
    $allowed=bioco_media_binding_value('stale',$context);
    $context['settings']['image']='invalid';$invalid=bioco_media_binding_value('stale',$context);
    echo json_encode([$denied,$allowed,$invalid]);
    ''', payload)
    assert result == ["", 'Alt "113" & field', ""]
