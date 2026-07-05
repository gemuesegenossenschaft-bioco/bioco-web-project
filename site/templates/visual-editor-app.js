"use strict";(()=>{function Te(e){return typeof e=="object"&&e!==null&&!Array.isArray(e)}function K(e){return typeof e=="string"&&e.length>0}function ut(e){return Array.isArray(e)?e.filter(t=>K(t)):[]}function ht(e){if(!Te(e))return{};let t={};for(let[i,a]of Object.entries(e))Array.isArray(a)&&(t[i]=ut(a));return t}function ki(e){return e==="localhost"||e.endsWith(".localhost")?!0:/^[0-9.]+$/.test(e)||e.includes(":")}function _i(e){let t=new URL(e),i=[t.origin];if(!ki(t.hostname)){let a=t.hostname.startsWith("www.")?t.hostname.slice(4):`www.${t.hostname}`,s=new URL(t.origin);s.hostname=a,s.origin!==t.origin&&i.push(s.origin)}return i}function wi(e){if(!Array.isArray(e))return[];let t=[];for(let i of e){if(!Te(i))continue;let a=Number(i.id);!Number.isFinite(a)||a<=0||K(i.path)&&t.push({id:a,title:typeof i.title=="string"?i.title:"",path:i.path,template:typeof i.template=="string"?i.template:""})}return t}function Ci(e){if(!Te(e))return{};let t={};for(let[i,a]of Object.entries(e))Te(a)&&(!K(a.type)||!K(a.listEndpoint)||(t[i]={type:a.type,root:K(a.root)?a.root:i,label:K(a.label)?a.label:i,listEndpoint:a.listEndpoint,addLabel:K(a.addLabel)?a.addLabel:"Neuen Eintrag erstellen"}));return t}function Ti(e){return Array.isArray(e)?e.filter(t=>Te(t)&&K(t.key)&&K(t.label)):[]}function Ei(e){let t=Te(e)?e:{};return{heroBaseFields:ut(t.heroBaseFields),sectionBaseFields:ut(t.sectionBaseFields),fieldMappings:ht(t.fieldMappings),heroFieldMappings:ht(t.heroFieldMappings),buttonFieldMappings:ht(t.buttonFieldMappings)}}function Ii(e){return ut(e).filter(t=>t!=="null")}function Ri(e){if(!Te(e))throw new Error("visual-editor shell config missing or not an object");if(!K(e.siteUrl))throw new Error("visual-editor shell config: siteUrl missing");let t;try{t=_i(e.siteUrl)}catch(a){throw new Error(`visual-editor shell config: siteUrl is not a valid URL: ${e.siteUrl}`)}if(!K(e.apiRoot))throw new Error("visual-editor shell config: apiRoot missing");if(!K(e.pageEditUrl))throw new Error("visual-editor shell config: pageEditUrl missing");let i=[...t];for(let a of Ii(e.allowedOrigins))i.includes(a)||i.push(a);return{siteUrl:e.siteUrl.replace(/\/+$/,""),apiRoot:e.apiRoot,adminUrl:typeof e.adminUrl=="string"?e.adminUrl:"",pageEditUrl:e.pageEditUrl,visualEditorUrl:typeof e.visualEditorUrl=="string"?e.visualEditorUrl:"",draftSecret:typeof e.draftSecret=="string"?e.draftSecret:"",pages:wi(e.pages),collections:Ci(e.collections),componentRegistry:Ti(e.componentRegistry),focusFields:Ei(e.focusFields),iframeOrigins:i}}function nn(e){let t=e.getElementById("ve-config");if(!t||!t.textContent)throw new Error("visual-editor shell config element #ve-config missing");return Ri(JSON.parse(t.textContent))}var l={toolbarLogo:"bioco Visual Editor",statusDisconnected:"Nicht verbunden",statusConnected:"Verbunden",modeEdit:"Edit",modeBrowse:"Browse",btnRefresh:"Neu laden",btnPresets:"Vorlagen",btnPwAdmin:"PW Admin",btnBack:"Zurück",sidebarKickerPage:"Seite",sidebarDefaultTitle:"Startseite",sidebarPathPlaceholder:"In der Vorschau navigieren, um eine Seite zu bearbeiten.",btnAddSection:"Abschnitt hinzufügen",pageNotEditable:"Nicht bearbeitbar",pageEditableFallback:"Bearbeitbare Seite",pageUnavailable:"Seite im Visual Editor nicht verfügbar",emptyNoPage:"In der Vorschau navigieren, um eine Seite zu bearbeiten.",emptyNoSections:"Noch keine Abschnitte vorhanden. Füge rechts oben einen Abschnitt hinzu.",emptyNoSelection:"Wähle einen Abschnitt oder ein Feld direkt in der Vorschau.",dirtyPill:"UNGESPEICHERT",untitledSection:"(kein Titel)",duplicateTitle:"Abschnitt kopieren",deleteTitle:"Abschnitt löschen",copySuffix:" (Kopie)",newSectionTitle:"Neuer Abschnitt",btnDiscard:"Entwurf verwerfen",btnPublish:"Publizieren",btnPublishing:"Publiziert...",statusLoadingSections:"Abschnitte laden...",busyLoadingSections:"Abschnitte laden…",statusLoadingPreview:"Vorschau laden...",busyLoadingPreview:"Vorschau laden…",statusPreviewFailed:"Vorschau konnte nicht verbunden werden",statusDraftSaved:"Entwurf lokal gespeichert",statusDraftDiscarded:"Entwurf verworfen",statusPublished:"Publiziert",statusPublishedLive:"Publiziert & live",statusPublishedStaleBuild:"Publiziert, aber Build nicht aktualisiert",errorPublishFailed:"Publizieren fehlgeschlagen",errorLoadFailed:"Fehler beim Laden",busyPublishing:"Änderungen publizieren…",statusUndo:"Rückgängig",statusRedo:"Wiederhergestellt",statusStaleDraftDiscarded:"Veralteter Entwurf verworfen, weil die Seite inzwischen geändert wurde.",statusDraftRestored:"Lokaler Entwurf wiederhergestellt.",statusConflictsResolved:"Konflikte gelöst. Bitte prüfen und erneut publizieren.",statusServerChangesAdopted:"Serveränderungen übernommen. Bitte erneut publizieren.",statusConflictsRetry:"Konflikte gelöst. Erneut publizieren.",statusOrderUpdated:"Reihenfolge im Entwurf aktualisiert",statusSectionAdded:"Abschnitt zum Entwurf hinzugefügt",statusSectionDeleted:"Abschnitt im Entwurf gelöscht",statusSectionDuplicated:"Abschnitt im Entwurf dupliziert. Medien prüfen.",statusMediaSelected:"Medium im Entwurf ausgewählt",statusPresetInserted:"Vorlage eingefügt",statusTypeAddedSuffix:" hinzugefuegt",busyDefault:"Bitte warten…",busyBody:"Der Editor verarbeitet gerade deine Aktion. Andere Interaktionen sind kurz gesperrt.",confirmDiscard:"Lokalen Entwurf wirklich verwerfen?",confirmDirtyAction:e=>`Ungespeicherte Änderungen vorhanden. "${e}" trotzdem ausführen?`,confirmDeleteSection:e=>`Abschnitt "${e}" wirklich löschen?`,confirmFieldConflict:(e,t)=>`Konflikt in Abschnitt "${e}" Feld "${t}". OK = lokal behalten, Abbrechen = Server übernehmen.`,confirmOrderConflict:"Abschnittsreihenfolge-Konflikt. OK = lokale Reihenfolge behalten, Abbrechen = Server-Reihenfolge übernehmen.",actionReload:"Neu laden",actionPageSwitch:"Seitenwechsel",alertPwFocusNeedsCleanDraft:"ProcessWire-Fokus ist nur ohne offenen Entwurf verfügbar. Bitte zuerst publizieren oder den Entwurf verwerfen.",alertPwFocusPublishFirst:"Dieser Abschnitt existiert nur im lokalen Entwurf. Bitte zuerst publizieren, dann in ProcessWire öffnen.",alertPwFocusUnavailable:"ProcessWire-Fokus konnte für dieses Ziel nicht vorbereitet werden.",openInPw:"→ In PW öffnen",ownershipVe:"Visual Editor",ownershipPw:"ProcessWire",infoCardPage:"Seite",infoCardMode:"Modus",infoCardStatus:"Status",infoCardDraft:"Entwurf",modeEditDescription:"Navigation über die echte Website, Bearbeitung direkt im Layout.",modeBrowseDescription:"Browse: Seite verhält sich wie normale Vorschau.",draftOpenDescription:"Lokaler Entwurf gespeichert und noch nicht publiziert.",draftNoneDescription:"Keine offenen Entwürfe.",draftDirtyCount:e=>`${e} Abschnitt(e) ungespeichert. Klicke "Publizieren".`,sectionFallbackBadge:"Abschnitt",fieldFallback:"Feld",buttonLabel:e=>`Button ${e+1}`,fieldLabels:{title:"Titel",eyebrow:"Eyebrow",text:"Text",media:"Bild / Medien",component:"Komponente",video:"Video",videoTitle:"Video Titel"},fieldHintDefaultNoField:"Klicke ein Feld in der Vorschau an, um inline zu bearbeiten.",fieldHints:{text:"Rich Text wird direkt im iframe bearbeitet. Änderungen bleiben lokal, bis du publizierst.",media:"Alt-Text und Medienauswahl laufen über das Overlay direkt im iframe.",component:"Komponentenname wird inline geändert. Komponentenspezifische Optionen sind in V1 noch begrenzt.",video:"Video-URL und Titel können direkt im Overlay bearbeitet werden.",videoTitle:"Video-URL und Titel können direkt im Overlay bearbeitet werden.",button:"Text, Link und Variante werden inline im Button-Overlay geändert."},fieldHintDefault:"Dieses Feld wird direkt in der Vorschau bearbeitet.",veRowHero:[["Headline","Klicken in der Vorschau"],["Untertitel","Klicken in der Vorschau"],["Bild Alt-Text","Via Bild-Overlay"]],veRowTitle:["Titel","Klicken in der Vorschau"],veRowEyebrow:["Eyebrow","Klicken in der Vorschau"],veRowText:["Text","Klicken → Rich-Text-Editor"],veRowLayoutTheme:["Layout & Thema","Via Abschnitt-Overlay"],veRowBgOverlay:["Hintergrundfarbe & Overlay","Via Abschnitt-Overlay"],veRowButtons:["Buttons","Klicken auf Button → Overlay"],veRowMedia:["Bild (aus Mediathek)","Klicken auf Bild → Overlay"],veRowMediaMeta:["Alt-Text, Helligkeit/Kontrast","Im Bild-Overlay"],veRowVideo:["Video-URL & Titel","Via Video-Overlay"],veRowComponentConfig:["Komponenten-Config","Via Komponenten-Overlay"],pwRowHeroImage:"Hero-Bild (Datei)",pwRowHeroAll:"Alle Hero-Felder",pwRowImages:"Bild-Datei(en)",pwRowAllFields:"Alle Felder (Vollansicht)",configEditorHeader:"Komponenten-Config",mediaModalTitle:"Mediathek",btnClose:"Schliessen",mediaLoading:"Medien werden geladen…",mediaEmpty:"Keine Medien gefunden.",mediaLoadFailed:"Medien konnten nicht geladen werden",mediaFallbackName:"Medium",presetModalTitle:"Abschnitt-Vorlagen",presetSearchPlaceholder:"Suche...",presetAllCategories:"Alle Kategorien",presetLoading:"Vorlagen werden geladen…",presetEmpty:"Keine Vorlagen gefunden.",presetLoadFailed:"Vorlagen konnten nicht geladen werden",presetInsert:"Einfügen",presetFallbackName:"Vorlage",addModalTitle:"Abschnitt hinzufügen",addSearchPlaceholder:"Typ suchen...",addAllFilter:"Alle",addEmpty:"Kein passender Abschnittstyp gefunden.",actionAdd:"Hinzufuegen",actionCopy:"Kopieren",actionDelete:"Löschen",actionSort:"Sortieren",actionMove:"Verschieben",actionDuplicate:"Duplizieren",collectionSuffix:" · Sammlung (ProcessWire)",collectionStatus:e=>`Sammlung: ${e}`,collectionDescription:e=>`Diese Einträge liegen als einzelne Seiten unter ${e} und werden direkt in ProcessWire bearbeitet.`,collectionEntriesHeader:"Einträge",collectionDateLabel:"Datum",collectionLoading:"Laden…",collectionEmpty:"Noch keine Einträge. Erstelle den ersten oben.",collectionLoadFailed:"Einträge konnten nicht geladen werden.",collectionEntryUntitled:"(ohne Titel)",collectionBadgePast:"Vergangen",collectionBadgeUpcoming:"Bevorstehend",busyCreatingEntry:"Eintrag erstellen…",statusEntryCreated:"Eintrag erstellt — in ProcessWire geöffnet",errorEntryCreateFailed:"Erstellen fehlgeschlagen",layoutLabels:{hero:"Hero",split_media_text:"Bild + Text",split_text_media:"Text + Bild",full_width_banner:"Banner",media_grid:"Bildergalerie",video_embed:"Video",rich_text:"Nur Text",component:"Komponente"},coreLayoutDescriptions:{rich_text:"Einfacher Textblock mit optionalen Buttons.",split_media_text:"Bild links, Text rechts.",split_text_media:"Text links, Bild rechts.",full_width_banner:"Vollbreites Bild mit Text-Overlay.",media_grid:"Mehrspaltige Bildergalerie.",video_embed:"Video-Einbettung (YouTube, Vimeo)."},addCategoryBase:"Basis",addCategoryOther:"Sonstiges",componentCategories:{page_intro:"Layout",media_text:"Layout",cards_grid:"Layout",gallery_strip:"Layout",text_columns:"Layout",cta_band:"Layout",timeline_header:"Timeline",timeline_item:"Timeline",contact_form:"Formulare",membership_form:"Formulare",subscribe_form:"Formulare",visit_day_form:"Formulare",waiting_list_form:"Formulare",pricing_calculator:"Interaktiv",events_feed:"Interaktiv",schnuppertage:"Interaktiv",saisonkalender:"Interaktiv",gallery:"Interaktiv",depot_map:"Karten",geisshof_map:"Karten"},addCategoryOrder:["Basis","Layout","Timeline","Formulare","Interaktiv","Karten"]};var qe=class extends Error{constructor(t,i,a={}){super(t),this.name="ApiError",this.status=i,this.data=a}};function rn(e,t){return e.replace(/\/+$/,"")+"/"+t.replace(/^\/+/,"")}async function on(e){try{let t=await e.json();return typeof t=="object"&&t!==null?t:{}}catch(t){return{}}}function ln(e,t,i){let a=typeof t.error=="string"&&t.error?t.error:i;return new qe(a,e.status,t)}function an(e,t=fetch){async function i(s,d,c={}){let m=await t(rn(e.apiRoot,s),{credentials:"include"}),f=await on(m);if(!m.ok||c.requireSuccess&&!f.success)throw ln(m,f,d);return f}async function a(s,d,c){let m=await t(rn(e.apiRoot,s),{method:"POST",headers:{"Content-Type":"application/json"},credentials:"include",body:JSON.stringify(d)}),f=await on(m);if(!m.ok||!f.success)throw ln(m,f,c);return f}return{fetchSections(s){let d=s==="/"?"content/homepage":"content/sections/"+encodeURIComponent(s.replace(/^\/|\/$/g,""));return i(d,l.errorLoadFailed)},publish(s){return a("content-publish",{...s},l.errorPublishFailed)},saveSectionFields({sectionPwId:s,pageId:d,fields:c}){return a("content-save",s?{sectionPwId:s,fields:c}:{pageId:d,fields:c},l.errorPublishFailed)},addSection(s,d){return a("sections-add",{pageId:s,layout:d},l.errorLoadFailed)},deleteSection(s,d){return a("sections-delete",{pageId:s,sectionPwId:d},l.errorLoadFailed)},reorderSections(s,d){return a("sections-reorder",{pageId:s,order:d},l.errorLoadFailed)},createCollectionEntry(s,d){return a("collection-create",{type:s,date:d},l.errorEntryCreateFailed)},async fetchMediaFiles(){let s=await i("media-files",l.mediaLoadFailed,{requireSuccess:!0});return Array.isArray(s.files)?s.files:[]},async fetchPresets(){let s=await i("content/presets",l.presetLoadFailed,{requireSuccess:!0});return Array.isArray(s.presets)?s.presets:[]},fetchCollectionEntries(s){return i(s,l.collectionLoadFailed)}}}function sn(e){let t=[];for(let i of["upcoming","past"]){let a=e[i];if(Array.isArray(a))for(let s of a)typeof s!="object"||s===null||t.push({...s,_status:i})}return t}function dn(e){if(e.revalidated===!1){let t=e.revalidateError?` (${e.revalidateError})`:"";return{text:`${l.statusPublishedStaleBuild}${t}`,cls:"is-error"}}return{text:l.statusPublishedLive,cls:"is-ready"}}var cn={status:"idle",busy:!1,busyLabel:"",iframeReady:!1,activePath:null,selectedSectionId:null,error:null,revalidated:null};function un(e,t){var i,a;switch(t.type){case"iframe-ready":return{...e,iframeReady:!0,activePath:t.path};case"edit":return e.busy||e.status==="saving"?e:{...e,status:"dirty",error:null};case"select-section":return e.busy||e.selectedSectionId===t.sectionId?e:{...e,selectedSectionId:t.sectionId};case"discard":return e.busy||e.status!=="dirty"&&e.status!=="error"?e:{...e,status:"idle",error:null};case"publish-start":return e.status!=="dirty"&&e.status!=="error"?e:{...e,status:"saving",busy:!0,busyLabel:(i=t.busyLabel)!=null?i:"Publizieren…",error:null};case"publish-success":return e.status!=="saving"?e:{...e,status:"published",busy:!1,busyLabel:"",revalidated:t.revalidated,error:null};case"publish-failure":return e.status!=="saving"?e:{...e,status:"error",busy:!1,busyLabel:"",error:t.error};case"busy-start":return e.status==="saving"?e:{...e,busy:!0,busyLabel:(a=t.busyLabel)!=null?a:""};case"busy-end":return e.status==="saving"||!e.busy&&e.busyLabel===""?e:{...e,busy:!1,busyLabel:""}}}function pn(e=cn){let t=e,i=new Set;return{getState:()=>t,dispatch(a){let s=un(t,a);if(s===t)return!1;t=s;for(let d of Array.from(i))d(t,a);return!0},subscribe(a){return i.add(a),()=>{i.delete(a)}}}}var vt="bioco:visual-editor:",Fi=["duplicate","move-up","move-down","delete"];function xt(e){return typeof e=="object"&&e!==null&&!Array.isArray(e)}function R(e){return typeof e=="string"&&e.length>0}function Mi(e){return Array.isArray(e)?e.filter(t=>typeof t=="string"):[]}function Li(e){if(!xt(e))return{};let t={};for(let[i,a]of Object.entries(e))Array.isArray(a)&&(t[i]=a.filter(s=>typeof s=="string"));return t}function fn(e){return!R(e.sectionId)||!R(e.field)||!R(e.kind)?null:{sectionId:e.sectionId,field:e.field,kind:e.kind,inline:e.inline!==!1,...typeof e.buttonIndex=="number"?{buttonIndex:e.buttonIndex}:{},...R(e.targetField)?{targetField:e.targetField}:{}}}function gn(e){return!R(e.sectionId)||!R(e.field)?null:{sectionId:e.sectionId,field:e.field,value:e.value,...typeof e.buttonIndex=="number"?{buttonIndex:e.buttonIndex}:{},...R(e.configKey)?{configKey:e.configKey}:{}}}var Pi={"save-state":e=>({type:"save-state",mode:e.mode==="browse"?"browse":"edit",dirty:!!e.dirty,saving:!!e.saving,busy:!!e.busy,busyLabel:typeof e.busyLabel=="string"?e.busyLabel:"",message:typeof e.message=="string"?e.message:"",selectedSectionId:R(e.selectedSectionId)?e.selectedSectionId:null,presetTagsByComponent:Li(e.presetTagsByComponent)}),"section-highlight":e=>({type:"section-highlight",sectionId:R(e.sectionId)?e.sectionId:null}),"field-highlight":e=>{let t=fn(e);return t?{type:"field-highlight",...t}:null},"field-reset":()=>({type:"field-reset"}),"section-scroll":e=>R(e.sectionId)?{type:"section-scroll",sectionId:e.sectionId}:null,"section-update":e=>!R(e.sectionId)||!R(e.field)?null:{type:"section-update",sectionId:e.sectionId,field:e.field,value:e.value},"sections-replace":e=>Array.isArray(e.sections)?{type:"sections-replace",sections:e.sections}:null,"save-result":e=>typeof e.success!="boolean"?null:{type:"save-result",success:e.success,...typeof e.revalidated=="boolean"?{revalidated:e.revalidated}:{},...R(e.error)?{error:e.error}:{}},ready:e=>typeof e.path!="string"?null:{type:"ready",path:e.path,sectionIds:Mi(e.sectionIds)},"section-click":e=>R(e.sectionId)?{type:"section-click",sectionId:e.sectionId}:null,"field-select":e=>{let t=fn(e);return t?{type:"field-select",...t}:null},"field-change":e=>{let t=gn(e);return t?{type:"field-change",...t}:null},"field-commit":e=>{let t=gn(e);return t?{type:"field-commit",...t}:null},"media-request":e=>R(e.sectionId)?{type:"media-request",sectionId:e.sectionId,...R(e.targetField)?{targetField:e.targetField}:{}}:null,"open-processwire":e=>R(e.sectionId)?{type:"open-processwire",sectionId:e.sectionId,...R(e.field)?{field:e.field}:{},...R(e.kind)?{kind:e.kind}:{},...typeof e.inline=="boolean"?{inline:e.inline}:{},...typeof e.buttonIndex=="number"?{buttonIndex:e.buttonIndex}:{},...R(e.targetField)?{targetField:e.targetField}:{}}:null,"section-action":e=>!R(e.sectionId)||!Fi.includes(e.action)?null:{type:"section-action",sectionId:e.sectionId,action:e.action}},Ai=["save-state","section-highlight","field-highlight","field-reset","section-scroll","section-update","sections-replace","save-result"],St=["ready","section-click","field-select","field-change","field-commit","media-request","open-processwire","section-action"];var ar=new Set(Ai),sr=new Set(St);function mn(e,t){return{type:e,...t}}function bn(e){let{type:t,...i}=e;return{type:`${vt}${t}`,...i}}function Bi(e,t){if(!xt(e))return null;let i=e.type;if(typeof i!="string"||t&&!t.has(i))return null;let a=Pi[i];return a?a(e):null}function yn(e,t){if(!Di(e.origin,t)||!xt(e.data))return null;let i=e.data.type;return typeof i!="string"||!i.startsWith(vt)?null:Bi({...e.data,type:i.slice(vt.length)})}function Di(e,t){return!R(e)||e==="null"?!1:t.includes(e)}var Oi=new Set(St);function hn(e){let t=e.defaultTargetOrigin,i=a=>{let s=yn({origin:a.origin,data:a.data},e.origins);s&&Oi.has(s.type)&&(t=a.origin,e.onMessage(s,a.origin))};return e.listenWindow.addEventListener("message",i),{send(a,s){let d=e.getTargetWindow();d&&d.postMessage(bn(mn(a,s)),t)},targetOrigin:()=>t,destroy(){e.listenWindow.removeEventListener("message",i)}}}var vn=[{key:"contact_form",label:"Kontaktformular",kind:"renderable",frontendTarget:{file:"frontend/components/forms/ContactForm.tsx",export:"ContactForm"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"membership_form",label:"Mitgliedschaftsformular",kind:"renderable",frontendTarget:{file:"frontend/components/forms/MembershipForm.tsx",export:"MembershipForm"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"subscribe_form",label:"Newsletter-Formular",kind:"renderable",frontendTarget:{file:"frontend/components/forms/SubscribeForm.tsx",export:"SubscribeForm"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"visit_day_form",label:"Schnuppertag-Formular",kind:"renderable",frontendTarget:{file:"frontend/components/forms/VisitDayForm.tsx",export:"VisitDayForm"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"waiting_list_form",label:"Wartelisten-Formular",kind:"renderable",frontendTarget:{file:"frontend/components/forms/WaitingListForm.tsx",export:"WaitingListForm"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"pricing_calculator",label:"Preisrechner",kind:"renderable",frontendTarget:{file:"frontend/components/PricingCalculator.tsx",export:"PricingCalculator"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"events_feed",label:"Events-Feed",kind:"renderable",frontendTarget:{file:"frontend/components/EventsSection.tsx",export:"EventsSection"},cmsFields:["section_component","section_title","section_text","section_config","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],configSchema:[{key:"variant",label:"Darstellung",type:"select",options:[{label:"Standard",value:"standard"},{label:"Banner",value:"banner"}]},{key:"limit",label:"Anzahl Einträge",type:"number",min:1,max:12}],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper. Darstellung 'Banner' rendert EventsBanner (kompakte Liste mit 'Alle Events ansehen'-Link, wie /kundenportal); 'Standard' rendert EventsSection. 'Anzahl Einträge' begrenzt die Banner-Liste."},{key:"schnuppertage",label:"Schnuppertage",kind:"renderable",frontendTarget:{file:"frontend/components/SchnuppertageSection.tsx",export:"SchnuppertageSection"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"group_cards",label:"Gruppen-Karten",kind:"renderable",frontendTarget:{file:"frontend/components/GroupCardsSection.tsx",export:"GroupCardsSection"},cmsFields:["section_component","section_title","section_text"],notes:"Karten-Grid der Arbeitsgruppen (Mitmachen). Die Karten selbst kommen live aus dem CMS-Endpoint /api/content/groups; Titel und Einleitungstext kommen aus dem Component-Section-Wrapper."},{key:"depot_map",label:"Depot-Karte",kind:"renderable",frontendTarget:{file:"frontend/components/DepotMap.tsx",export:"DepotMap"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"geisshof_map",label:"Geisshof-Karte",kind:"renderable",frontendTarget:{file:"frontend/components/GeisshofMap.tsx",export:"GeisshofMap"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"saisonkalender",label:"Saisonkalender",kind:"renderable",frontendTarget:{file:"frontend/components/Saisonkalender.tsx",export:"Saisonkalender"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"gallery",label:"Galerie",kind:"renderable",frontendTarget:{file:"frontend/components/Gallery.tsx",export:"Gallery"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant"],notes:"Titel, Text und Buttons kommen aus dem Component-Section-Wrapper."},{key:"page_intro",label:"Seiten-Einstieg",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"PageIntroBlock"},cmsFields:["section_component","section_title","section_eyebrow","section_text","section_config"],notes:"Intro-Block mit frei konfigurierbarer Textbreite und Ausrichtung.",defaultConfig:{containerWidth:"lg",textWidth:"normal",align:"left"},configSchema:[{key:"containerWidth",label:"Container",type:"select",options:[{label:"S",value:"sm"},{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"},{label:"Voll",value:"full"}]},{key:"textWidth",label:"Textbreite",type:"select",options:[{label:"Schmal",value:"narrow"},{label:"Normal",value:"normal"},{label:"Breit",value:"wide"}]},{key:"align",label:"Ausrichtung",type:"select",options:[{label:"Links",value:"left"},{label:"Zentriert",value:"center"}]}]},{key:"media_text",label:"Medien + Text",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"MediaTextBlock"},cmsFields:["section_component","section_title","section_eyebrow","section_text","section_image","section_images","image_alt","button_text","button_href","button_variant","button2_text","button2_href","button2_variant","section_config"],notes:"Flexibler Zwei-Spalten-Block für /wir und andere CMS-Seiten.",defaultConfig:{containerWidth:"xl",mediaSide:"left",mediaWidth:"50",mediaRatio:"4:3",mediaFit:"cover",verticalAlign:"center",gap:"lg",rounded:"lg"},configSchema:[{key:"containerWidth",label:"Container",type:"select",options:[{label:"S",value:"sm"},{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"},{label:"Voll",value:"full"}]},{key:"mediaSide",label:"Bildseite",type:"select",options:[{label:"Links",value:"left"},{label:"Rechts",value:"right"}]},{key:"mediaWidth",label:"Bildbreite",type:"select",options:[{label:"40%",value:"40"},{label:"50%",value:"50"},{label:"60%",value:"60"}]},{key:"mediaRatio",label:"Seitenverhältnis",type:"select",options:[{label:"1:1",value:"1:1"},{label:"4:3",value:"4:3"},{label:"3:4",value:"3:4"},{label:"16:9",value:"16:9"},{label:"Auto",value:"auto"}]},{key:"mediaFit",label:"Bildmodus",type:"select",options:[{label:"Cover",value:"cover"},{label:"Contain",value:"contain"}]},{key:"verticalAlign",label:"Vertikal",type:"select",options:[{label:"Oben",value:"start"},{label:"Mitte",value:"center"},{label:"Unten",value:"end"}]},{key:"gap",label:"Abstand",type:"select",options:[{label:"S",value:"sm"},{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"}]},{key:"rounded",label:"Radius",type:"select",options:[{label:"Kein",value:"none"},{label:"S",value:"sm"},{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"}]}]},{key:"cards_grid",label:"Kartenraster",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"CardsGridBlock"},cmsFields:["section_component","section_title","section_eyebrow","section_text","section_images","section_image","image_alt","section_config"],notes:"Bildkarten-Raster aus den CMS-Bildern einer Section.",defaultConfig:{containerWidth:"xl",columnsDesktop:"3",columnsMobile:"1",cardStyle:"soft",mediaRatio:"3:4",mediaFit:"cover",gap:"lg",rounded:"md"},configSchema:[{key:"containerWidth",label:"Container",type:"select",options:[{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"},{label:"Voll",value:"full"}]},{key:"columnsDesktop",label:"Spalten Desktop",type:"select",options:[{label:"2",value:"2"},{label:"3",value:"3"},{label:"4",value:"4"}]},{key:"columnsMobile",label:"Spalten Mobile",type:"select",options:[{label:"1",value:"1"},{label:"2",value:"2"}]},{key:"cardStyle",label:"Kartenstil",type:"select",options:[{label:"Plain",value:"plain"},{label:"Soft",value:"soft"},{label:"Outlined",value:"outlined"}]},{key:"mediaRatio",label:"Bildratio",type:"select",options:[{label:"1:1",value:"1:1"},{label:"4:3",value:"4:3"},{label:"3:4",value:"3:4"}]},{key:"mediaFit",label:"Bildmodus",type:"select",options:[{label:"Cover",value:"cover"},{label:"Contain",value:"contain"}]},{key:"gap",label:"Abstand",type:"select",options:[{label:"S",value:"sm"},{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"}]}]},{key:"gallery_strip",label:"Galerie-Streifen",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"GalleryStripBlock"},cmsFields:["section_component","section_title","section_text","section_images","section_image","image_alt","section_config"],notes:"Mehrere Bilder in einem einfachen Raster oder Streifen.",defaultConfig:{containerWidth:"xl",columnsDesktop:"3",columnsMobile:"1",mediaRatio:"4:3",mediaFit:"cover",gap:"lg",rounded:"lg"},configSchema:[{key:"columnsDesktop",label:"Spalten Desktop",type:"select",options:[{label:"2",value:"2"},{label:"3",value:"3"},{label:"4",value:"4"}]},{key:"columnsMobile",label:"Spalten Mobile",type:"select",options:[{label:"1",value:"1"},{label:"2",value:"2"}]},{key:"mediaRatio",label:"Bildratio",type:"select",options:[{label:"1:1",value:"1:1"},{label:"4:3",value:"4:3"},{label:"16:9",value:"16:9"}]},{key:"mediaFit",label:"Bildmodus",type:"select",options:[{label:"Cover",value:"cover"},{label:"Contain",value:"contain"}]},{key:"gap",label:"Abstand",type:"select",options:[{label:"S",value:"sm"},{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"}]},{key:"rounded",label:"Radius",type:"select",options:[{label:"Kein",value:"none"},{label:"S",value:"sm"},{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"}]}]},{key:"text_columns",label:"Textspalten",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"TextColumnsBlock"},cmsFields:["section_component","section_title","section_eyebrow","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant","section_config"],notes:"Mehrspaltiger Textblock für Mission, Geschichte und ähnliche Inhalte.",defaultConfig:{containerWidth:"lg",columnsDesktop:"2",columnsMobile:"1",gap:"lg",cardStyle:"plain"},configSchema:[{key:"containerWidth",label:"Container",type:"select",options:[{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"},{label:"Voll",value:"full"}]},{key:"columnsDesktop",label:"Spalten Desktop",type:"select",options:[{label:"1",value:"1"},{label:"2",value:"2"},{label:"3",value:"3"},{label:"4",value:"4"}]},{key:"columnsMobile",label:"Spalten Mobile",type:"select",options:[{label:"1",value:"1"},{label:"2",value:"2"}]},{key:"gap",label:"Abstand",type:"select",options:[{label:"S",value:"sm"},{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"}]}]},{key:"timeline_header",label:"Timeline-Kopf",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"TimelineHeaderBlock"},cmsFields:["section_component","section_title","section_text","section_config"],notes:"Einleitender Block vor Timeline-Einträgen.",aliases:["timeline"],defaultConfig:{containerWidth:"lg",textWidth:"normal",align:"left"},configSchema:[{key:"containerWidth",label:"Container",type:"select",options:[{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"}]},{key:"textWidth",label:"Textbreite",type:"select",options:[{label:"Schmal",value:"narrow"},{label:"Normal",value:"normal"},{label:"Breit",value:"wide"}]},{key:"align",label:"Ausrichtung",type:"select",options:[{label:"Links",value:"left"},{label:"Zentriert",value:"center"}]}]},{key:"timeline_item",label:"Timeline-Eintrag",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"TimelineItemBlock"},cmsFields:["section_component","section_eyebrow","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant","section_config"],notes:"Ein Timeline-Eintrag mit Jahr, Titel, Text und optionalen Buttons.",aliases:["timeline-item"],defaultConfig:{containerWidth:"lg",emphasis:"normal"},configSchema:[{key:"containerWidth",label:"Container",type:"select",options:[{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"}]},{key:"emphasis",label:"Betonung",type:"select",options:[{label:"Normal",value:"normal"},{label:"Highlight",value:"highlight"}]}]},{key:"cta_band",label:"CTA-Band",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"CtaBandBlock"},cmsFields:["section_component","section_title","section_text","button_text","button_href","button_variant","button2_text","button2_href","button2_variant","section_config"],notes:"Breiter CTA-Block mit ein bis zwei Buttons.",defaultConfig:{containerWidth:"lg",align:"left",theme:"soft",rounded:"xl"},configSchema:[{key:"containerWidth",label:"Container",type:"select",options:[{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"},{label:"Voll",value:"full"}]},{key:"align",label:"Ausrichtung",type:"select",options:[{label:"Links",value:"left"},{label:"Zentriert",value:"center"}]},{key:"theme",label:"Stil",type:"select",options:[{label:"Light",value:"light"},{label:"Soft",value:"soft"},{label:"Accent",value:"accent"},{label:"Dark",value:"dark"}]},{key:"rounded",label:"Radius",type:"select",options:[{label:"Kein",value:"none"},{label:"S",value:"sm"},{label:"M",value:"md"},{label:"L",value:"lg"},{label:"XL",value:"xl"}]}]},{key:"pricing_table",label:"Preis-Tabelle (Abos)",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"PricingTableBlock"},cmsFields:["section_component","section_title","section_eyebrow","section_text","section_config","button_text","button_href","button_variant"],notes:"Abo-Preistabelle mit 3 Stufen. Titel und Einleitung aus dem Section-Wrapper, Tabellenzellen aus der Konfiguration.",defaultConfig:{containerWidth:"xl",workSuffix:"à 2 Stunden",tier1_name:"Halb",tier1_shares:"1 Anteilsschein",tier1_persons:1,tier1_price:"CHF 750.-",tier1_sharecost:"CHF 250.-",tier1_work:"10 Arbeitseinsätze",tier2_name:"Standard",tier2_shares:"2 Anteilsscheine",tier2_persons:2,tier2_price:"CHF 1'280.-",tier2_sharecost:"CHF 500.-",tier2_work:"20 Arbeitseinsätze",tier3_name:"Doppel",tier3_shares:"4 Anteilsscheine",tier3_persons:4,tier3_price:"CHF 2'350.-",tier3_sharecost:"CHF 1'000.-",tier3_work:"40 Arbeitseinsätze"},configSchema:[{key:"containerWidth",label:"Container",type:"select",options:[{label:"L",value:"lg"},{label:"XL",value:"xl"},{label:"Voll",value:"full"}]},{key:"workSuffix",label:"Mitarbeit-Zusatz",type:"text",placeholder:"à 2 Stunden"},{key:"tier1_name",label:"Stufe 1: Name",type:"text"},{key:"tier1_shares",label:"Stufe 1: Anteile",type:"text"},{key:"tier1_persons",label:"Stufe 1: Personen",type:"number",min:1,max:8},{key:"tier1_price",label:"Stufe 1: Jahrespreis",type:"text"},{key:"tier1_sharecost",label:"Stufe 1: Anteilskosten",type:"text"},{key:"tier1_work",label:"Stufe 1: Mitarbeit",type:"text"},{key:"tier2_name",label:"Stufe 2: Name",type:"text"},{key:"tier2_shares",label:"Stufe 2: Anteile",type:"text"},{key:"tier2_persons",label:"Stufe 2: Personen",type:"number",min:1,max:8},{key:"tier2_price",label:"Stufe 2: Jahrespreis",type:"text"},{key:"tier2_sharecost",label:"Stufe 2: Anteilskosten",type:"text"},{key:"tier2_work",label:"Stufe 2: Mitarbeit",type:"text"},{key:"tier3_name",label:"Stufe 3: Name",type:"text"},{key:"tier3_shares",label:"Stufe 3: Anteile",type:"text"},{key:"tier3_persons",label:"Stufe 3: Personen",type:"number",min:1,max:8},{key:"tier3_price",label:"Stufe 3: Jahrespreis",type:"text"},{key:"tier3_sharecost",label:"Stufe 3: Anteilskosten",type:"text"},{key:"tier3_work",label:"Stufe 3: Mitarbeit",type:"text"}]},{key:"accordion_item",label:"Akkordeon-Eintrag",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"AccordionItemBlock"},cmsFields:["section_component","section_title","section_text"],notes:"Aufklappbarer Eintrag (details/summary) im Stil des Demeter-Akkordeons. Titel = Zeile, Text = Inhalt."},{key:"steps",label:"Nummerierte Schritte",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"StepsBlock"},cmsFields:["section_component","section_title","section_config"],notes:"Bis zu 4 nummerierte Schritte mit Kreis-Nummer. Nur ausgefüllte Schritte werden angezeigt.",defaultConfig:{step1_title:"",step1_text:"",step2_title:"",step2_text:"",step3_title:"",step3_text:"",step4_title:"",step4_text:""},configSchema:[{key:"step1_title",label:"Schritt 1 — Titel",type:"text"},{key:"step1_text",label:"Schritt 1 — Text",type:"text"},{key:"step2_title",label:"Schritt 2 — Titel",type:"text"},{key:"step2_text",label:"Schritt 2 — Text",type:"text"},{key:"step3_title",label:"Schritt 3 — Titel",type:"text"},{key:"step3_text",label:"Schritt 3 — Text",type:"text"},{key:"step4_title",label:"Schritt 4 — Titel",type:"text"},{key:"step4_text",label:"Schritt 4 — Text",type:"text"}]},{key:"link_tiles",label:"Portal-Kacheln",kind:"renderable",frontendTarget:{file:"frontend/components/sections/RegisteredSectionComponents.tsx",export:"LinkTilesBlock"},cmsFields:["section_component","section_title","section_config"],notes:"Kachel-Raster mit Symbol, Titel, Text und optionalem Link. Nur Kacheln mit Titel werden angezeigt.",defaultConfig:{tile1_title:"",tile1_text:"",tile1_href:"",tile1_icon:"",tile2_title:"",tile2_text:"",tile2_href:"",tile2_icon:"",tile3_title:"",tile3_text:"",tile3_href:"",tile3_icon:"",tile4_title:"",tile4_text:"",tile4_href:"",tile4_icon:""},configSchema:[{key:"tile1_title",label:"Kachel 1 — Titel",type:"text"},{key:"tile1_text",label:"Kachel 1 — Text",type:"text"},{key:"tile1_href",label:"Kachel 1 — Link",type:"text"},{key:"tile1_icon",label:"Kachel 1 — Symbol (Emoji)",type:"text"},{key:"tile2_title",label:"Kachel 2 — Titel",type:"text"},{key:"tile2_text",label:"Kachel 2 — Text",type:"text"},{key:"tile2_href",label:"Kachel 2 — Link",type:"text"},{key:"tile2_icon",label:"Kachel 2 — Symbol (Emoji)",type:"text"},{key:"tile3_title",label:"Kachel 3 — Titel",type:"text"},{key:"tile3_text",label:"Kachel 3 — Text",type:"text"},{key:"tile3_href",label:"Kachel 3 — Link",type:"text"},{key:"tile3_icon",label:"Kachel 3 — Symbol (Emoji)",type:"text"},{key:"tile4_title",label:"Kachel 4 — Titel",type:"text"},{key:"tile4_text",label:"Kachel 4 — Text",type:"text"},{key:"tile4_href",label:"Kachel 4 — Link",type:"text"},{key:"tile4_icon",label:"Kachel 4 — Symbol (Emoji)",type:"text"}]}];var Ni=vn;function kt(e){return String(e||"").trim().toLowerCase()}var zi=Ni.reduce((e,t)=>{e[kt(t.key)]=t;for(let i of t.aliases||[])e[kt(i)]=t;return e},{});function xn(e){return e==null?e:JSON.parse(JSON.stringify(e))}function Vi(e){let t=kt(e);if(!t)return null;let i=zi[t];return i?{entry:i,matchedKey:t,canonicalKey:i.key}:null}function Ki(e){let t=Vi(e);return xn((t==null?void 0:t.entry.defaultConfig)||{})||{}}function Ze(e,t){return{...Ki(e),...xn(t||{})||{}}}function Ue(e){return e==null?e:JSON.parse(JSON.stringify(e))}function v(e,t=""){return String(e!=null?e:t)}function Ne(e,t){let i=Number(e);return Number.isFinite(i)?i:t}function Sn(e){return Array.isArray(e)?e.slice(0,2).map((t,i)=>({text:v(t==null?void 0:t.text),href:v(t==null?void 0:t.href),variant:v(t==null?void 0:t.variant,i===0?"primary":"secondary")})).filter(t=>t.text.trim()||t.href.trim()):[]}function wt(e){return Array.isArray(e)?e.map(t=>{let i=t;return i!=null&&i.url?{url:v(i.url),alt:v(i.alt),type:v(i.type,"image")||"image"}:null}).filter(Boolean):[]}function kn(e){let t=v(e.component),i={id:v(e.id),title:v(e.title),text:v(e.text),layout:v(e.layout,"rich_text")||"rich_text",theme:v(e.theme,"default")||"default"};e.pwId&&(i.pwId=Number(e.pwId)),typeof e.sort=="number"&&(i.sort=e.sort),e.eyebrow&&(i.eyebrow=v(e.eyebrow)),t&&(i.component=t),(t||e.config)&&(i.config=Ze(t,Ue(e.config||{}))),e.bgColor&&(i.bgColor=v(e.bgColor)),e.imageOverlay&&(i.imageOverlay=v(e.imageOverlay)),e.imageBrightness!=null&&(i.imageBrightness=Ne(e.imageBrightness,1)),e.imageContrast!=null&&(i.imageContrast=Ne(e.imageContrast,1)),e.imageSaturate!=null&&(i.imageSaturate=Ne(e.imageSaturate,1)),e.image&&(i.image=v(e.image)),e.imageAlt!=null&&(i.imageAlt=v(e.imageAlt)),e.imageData&&(i.imageData=Ue(e.imageData)),e.video&&(i.video={url:v(e.video.url),title:v(e.video.title)});let a=Sn(e.buttons);a.length&&(i.buttons=a);let s=wt(e.media);s.length&&(i.media=s,i.mediaItems=Ue(s),i.images=s.filter(c=>(c.type||"image")==="image").map(c=>({url:c.url,alt:c.alt||""})));let d=e;return Array.isArray(d.draftMediaItems)&&(i.draftMediaItems=Ue(d.draftMediaItems)),d.draftMedia&&(i.draftMedia=Ue(d.draftMedia)),i}function _t(e,t,i){let a=[...e.buttons||[]];for(;a.length<=t;)a.push({text:"",href:"",variant:a.length===0?"primary":"secondary"});return a[t]={...a[t],...i},{...e,buttons:a}}function _n(e,t){var i,a;switch(t.field){case"title":return{...e,title:v(t.value)};case"text":return{...e,text:v(t.value)};case"eyebrow":return{...e,eyebrow:v(t.value)};case"layout":return{...e,layout:v(t.value,"rich_text")||"rich_text"};case"theme":return{...e,theme:v(t.value,"default")||"default"};case"bgColor":return{...e,bgColor:v(t.value)};case"imageOverlay":return{...e,imageOverlay:v(t.value)};case"component":{let s=v(t.value);return{...e,component:s,config:Ze(s,e.config||{})}}case"config":{let s=Ze(e.component,e.config||{}),d=t.configKey?{...s,[t.configKey]:t.value}:Ue(t.value||{});return{...e,config:Ze(e.component,d)}}case"imageAlt":return{...e,imageAlt:v(t.value)};case"videoUrl":return{...e,video:{...e.video||{title:""},url:v(t.value)}};case"videoTitle":return{...e,video:{...e.video||{url:""},title:v(t.value)}};case"mediaItems":{let s=wt(t.value),d=s.filter(c=>(c.type||"image")==="image").map(c=>({url:c.url,alt:c.alt||""}));return{...e,media:s,images:d,image:((i=d[0])==null?void 0:i.url)||"",imageAlt:((a=d[0])==null?void 0:a.alt)||e.imageAlt||"",imageData:d[0]?{url:d[0].url,description:d[0].alt||e.imageAlt||""}:void 0}}case"buttons":return{...e,buttons:Sn(t.value)};case"video":return{...e,video:t.value&&typeof t.value=="object"?{url:v(t.value.url),title:v(t.value.title)}:null};case"media":return{...e,media:wt(t.value)};case"images":return{...e,images:Array.isArray(t.value)?t.value.map(s=>{let d=s;return d!=null&&d.url?{url:v(d.url),alt:v(d.alt)}:null}).filter(Boolean):[]};case"image":return{...e,image:v(t.value)};case"imageBrightness":return{...e,imageBrightness:Ne(t.value,1)};case"imageContrast":return{...e,imageContrast:Ne(t.value,1)};case"imageSaturate":return{...e,imageSaturate:Ne(t.value,1)};case"button_text":return _t(e,t.buttonIndex||0,{text:v(t.value)});case"button_href":return _t(e,t.buttonIndex||0,{href:v(t.value)});case"button_variant":return _t(e,t.buttonIndex||0,{variant:v(t.value,(t.buttonIndex||0)===0?"primary":"secondary")});default:return e}}var Ct="__hero__",Hi="bioco-ve-draft:v1:";function ve(e){return e==null?e:JSON.parse(JSON.stringify(e))}function q(e){return e?typeof e=="string"?e===Ct:e.id===Ct||e.layout==="hero":!1}function wn(e,t){let i=e;return!i||!i.assetId||!i.fileField||!i.fileName||!i.url?null:{assetId:Number(i.assetId),fileField:String(i.fileField),fileName:String(i.fileName),targetField:String(i.targetField||t),url:String(i.url),assetTitle:i.assetTitle?String(i.assetTitle):""}}function Ve(e){let t=e;if(!t||!t.id)return null;let i=kn(t),a=q(i)?"hero_image":"section_image",s=wn(t.draftMedia,a);if(s?i.draftMedia=s:delete i.draftMedia,Array.isArray(t.draftMediaItems)){let d=t.draftMediaItems.map(c=>wn(c,"section_images")).filter(c=>c!==null);d.length?i.draftMediaItems=d:delete i.draftMediaItems}else delete i.draftMediaItems;return i}function C(e){return(e||[]).map(Ve).filter(t=>t!==null)}function pt(e,t){let i=e||{};return Ve({id:Ct,pwId:t||void 0,title:String(i.headline||"Hero"),eyebrow:String(i.subtitle||""),image:String(i.image||""),imageAlt:String(i.imageAlt||""),layout:"hero",theme:"default"})}function Tt(e){return{id:e.id,pwId:e.pwId||null,title:e.title||"",text:e.text||"",layout:e.layout||"rich_text",theme:e.theme||"default",eyebrow:e.eyebrow||"",component:e.component||"",config:ve(e.config||{}),bgColor:e.bgColor||"",imageOverlay:e.imageOverlay||"",image:e.image||"",imageAlt:e.imageAlt||"",imageBrightness:e.imageBrightness==null?null:e.imageBrightness,imageContrast:e.imageContrast==null?null:e.imageContrast,imageSaturate:e.imageSaturate==null?null:e.imageSaturate,video:ve(e.video||null),media:ve(e.media||[]),mediaItems:ve(e.mediaItems||[]),buttons:ve(e.buttons||[]),draftMedia:ve(e.draftMedia||null),draftMediaItems:ve(e.draftMediaItems||[])}}function Cn(e){return JSON.stringify(Tt(e))}function Tn(e,t){return JSON.stringify(e.map(Tt))!==JSON.stringify(t.map(Tt))}function Et(e,t){let i={},a={};for(let c of t)a[c.id]=Cn(c);for(let c of e)a[c.id]!==Cn(c)&&(i[c.id]=!0);let s=t.map(c=>c.id).join("|"),d=e.map(c=>c.id).join("|");if(s!==d)for(let c of e)i[c.id]=!0;return i}function En(e,t){let i=_n(e,t);if(t.field==="mediaItems"){let a=Array.isArray(e.draftMediaItems)?e.draftMediaItems:[],s={};for(let d of a)!d||!d.url||(s[d.url]=s[d.url]||[]).push(d);i.draftMediaItems=(i.media||[]).map(d=>{var c;return(c=s[d.url])!=null&&c.length?s[d.url].shift():null}).filter(d=>d!==null)}return i}function In(e,t){var i;switch(e){case"component":return[{field:"component",value:t.component||""},{field:"config",value:t.config||{}}];case"config":return[{field:"config",value:t.config||{}}];case"videoUrl":return[{field:"video",value:t.video||null}];case"videoTitle":return[{field:"video",value:t.video||null},{field:"videoTitle",value:((i=t.video)==null?void 0:i.title)||""}];case"mediaItems":return[{field:"media",value:t.media||[]},{field:"images",value:t.images||[]},{field:"image",value:t.image||""}];case"button_text":case"button_href":case"button_variant":return[{field:"buttons",value:t.buttons||[]}];default:return[{field:e,value:t[e]}]}}function Rn(e,t,i){let a={assetId:t.assetId,assetTitle:t.assetTitle||"",fileField:t.fileField,fileName:t.fileName,targetField:i,url:t.url},s=e.imageAlt||t.assetTitle||e.title||"";return{...e,draftMedia:a,draftMediaItems:[ve(a)],image:t.url,imageAlt:s,imageData:{url:t.url,description:s},images:[{url:t.url,alt:s}],media:[{url:t.url,alt:s,type:"image"}]}}function Fn(e,t,i){var m,f;let a={assetId:t.assetId,assetTitle:t.assetTitle||"",fileField:t.fileField,fileName:t.fileName,targetField:i||"section_images",url:t.url},s=t.assetTitle||e.imageAlt||e.title||"",d=[...e.media||[],{url:t.url,alt:s,type:"image"}],c=d.map(B=>({url:B.url,alt:B.alt||""}));return{...e,media:d,images:c,image:e.image||((m=c[0])==null?void 0:m.url)||"",imageAlt:e.image?e.imageAlt:e.imageAlt||((f=c[0])==null?void 0:f.alt)||"",draftMediaItems:[...e.draftMediaItems||[],a]}}function It(e,t){return!e||!t?"":`${Hi}${e}:${t}`}function Mn(e,t){var a,s;let i=It(t.pageId,t.path);if(i)try{e.setItem(i,JSON.stringify({pageId:t.pageId,path:t.path,baseFingerprint:t.baseFingerprint||"",savedAt:Date.now(),sections:C(t.sections),activeSectionId:(a=t.activeSectionId)!=null?a:null,activeField:(s=t.activeField)!=null?s:null}))}catch(d){}}function ze(e,t,i){let a=It(t,i);if(a)try{e.removeItem(a)}catch(s){}}function Ln(e,t){var c;let i={sections:C(t.sections),restored:!1,message:"",activeSectionId:null,activeField:null},a=It(t.pageId,t.path);if(!a)return i;let s=null;try{let m=e.getItem(a);s=m?JSON.parse(m):null}catch(m){s=null}if(!s||!Array.isArray(s.sections))return i;if(String(s.baseFingerprint||"")!==String(t.fingerprint||""))return ze(e,t.pageId,t.path),{...i,message:l.statusStaleDraftDiscarded};let d=C(s.sections);return d.length?{sections:d,restored:!0,message:l.statusDraftRestored,activeSectionId:typeof s.activeSectionId=="string"?s.activeSectionId:null,activeField:(c=s.activeField)!=null?c:null}:(ze(e,t.pageId,t.path),i)}function de(e){return e==null?e:JSON.parse(JSON.stringify(e))}function Rt(e,t){return JSON.stringify(e==null?null:e)===JSON.stringify(t==null?null:t)}function Ft(e){let t={};for(let i of e||[])!i||!i.id||(t[i.id]=de(i));return t}function Pn(e,t,i,a){let s=Ft(e),d=Ft(t),c=Ft(i),m=new Set([...Object.keys(s),...Object.keys(d),...Object.keys(c)]),f={},B=[];for(let T of Array.from(m)){let Ie=s[T]||null,H=d[T]||null,W=c[T]||null;if(!H&&W){f[T]=de(W);continue}if(H&&!W){f[T]=de(H);continue}if(!H&&!W)continue;let le=de(W),Ke=new Set([...Object.keys(Ie||{}),...Object.keys(H||{}),...Object.keys(W||{})]);for(let j of Array.from(Ke)){let ee=Ie?Ie[j]:void 0,ae=H?H[j]:void 0,$=W?W[j]:void 0;if(Rt($,ae)){le[j]=de($);continue}if(Rt(ee,ae)){le[j]=de($);continue}if(Rt(ee,$)){le[j]=de(ae);continue}let et=a.keepLocalField(T,j);le[j]=de(et?$:ae),B.push({sectionId:T,field:j,keep:et?"local":"server"})}let ue=Ve(le);ue&&(f[T]=ue)}let k=(e||[]).map(T=>T.id).join("|"),Z=(t||[]).map(T=>T.id),N=(i||[]).map(T=>T.id),z=Z.join("|"),ce=N.join("|"),Q=!0;k!==z&&k!==ce&&z!==ce?Q=a.keepLocalOrder():k===ce&&k!==z&&(Q=!1);let Ee=Q?[...N]:[...Z];for(let T of Object.keys(f))Ee.includes(T)||Ee.push(T);let Qe=Ee.map(T=>f[T]).filter(T=>!!T);return{mergedSections:C(Qe),conflicts:B,keepLocalOrder:Q}}function Mt(e){return e.id==="__hero__"||e.layout==="hero"}function xe(e){let t=[];for(let i of e){let a=String(i||"").trim();a&&!t.includes(a)&&t.push(a)}return t}function Wi(e,t,i,a){var d;if(!e)return[];let s=String(t.field||"").trim();if(Mt(e))return xe(s?s==="media"?i.heroFieldMappings.media||i.heroBaseFields:i.heroFieldMappings[s]||i.heroBaseFields:i.heroBaseFields);if(!s){let c=a(e.component);return(d=c==null?void 0:c.cmsFields)!=null&&d.length?xe(c.cmsFields):xe(i.sectionBaseFields)}if(s==="button"){let c=String(t.buttonIndex!=null?t.buttonIndex:0);return xe(i.buttonFieldMappings[c]||i.buttonFieldMappings[0]||[])}return xe(s==="media"?[t.targetField||"section_image","image_alt"]:i.fieldMappings[s]||i.sectionBaseFields)}function ji(e,t,i){let a=[];return t&&a.push(`pageId=${encodeURIComponent(String(t))}`),i&&a.push(`path=${encodeURIComponent(i)}`),e+(a.length?`?${a.join("&")}`:"")}function An(e){let{section:t,request:i}=e;if(!e.pageId||!t)return{error:"missing_target"};if(!Mt(t)&&!t.pwId)return{error:"publish_first"};let a=Wi(t,i,e.focusFields,e.resolveComponent);if(!a.length)return{error:"missing_fields"};let s=[`id=${encodeURIComponent(String(e.pageId))}`,"veFocus=1",`vePageId=${encodeURIComponent(String(e.pageId))}`,`vePath=${encodeURIComponent(e.path||"")}`,`veSectionId=${encodeURIComponent(t.id||"")}`,`veFields=${encodeURIComponent(a.join(","))}`,`veReturn=${encodeURIComponent(ji(e.visualEditorUrl,e.pageId,e.path||""))}`];return!Mt(t)&&t.pwId&&s.push(`veSectionPwId=${encodeURIComponent(String(t.pwId))}`),i.field&&s.push(`veField=${encodeURIComponent(i.field)}`),i.kind&&s.push(`veKind=${encodeURIComponent(i.kind)}`),i.buttonIndex!=null&&s.push(`veButtonIndex=${encodeURIComponent(String(i.buttonIndex))}`),i.targetField&&s.push(`veTargetField=${encodeURIComponent(i.targetField)}`),t.component&&s.push(`veComponent=${encodeURIComponent(t.component)}`),{url:`${e.pageEditUrl}?${s.join("&")}`}}function $i(e,t){let i=Number(e);return Number.isFinite(i)?i:t}function Bn(e){if(!Array.isArray(e))return[];let t=[];for(let i of e){if(typeof i!="object"||i===null)continue;let a=i;typeof a.key!="string"||!a.key||a.type!=="select"&&a.type!=="range"&&a.type!=="text"&&a.type!=="number"||t.push({key:a.key,label:typeof a.label=="string"&&a.label?a.label:a.key,type:a.type,options:Array.isArray(a.options)?a.options.filter(s=>typeof s=="object"&&s!==null&&"value"in s):void 0,min:typeof a.min=="number"?a.min:void 0,max:typeof a.max=="number"?a.max:void 0,step:typeof a.step=="number"?a.step:void 0,placeholder:typeof a.placeholder=="string"?a.placeholder:void 0})}return t}function Dn(e){let{doc:t,schema:i,config:a,onChange:s}=e,d=t.createElement("div");d.className="ve-config-editor";for(let c of i){let m=t.createElement("div");m.className="ve-field-group";let f=t.createElement("label");f.textContent=c.label,m.appendChild(f);let B=a[c.key];if(c.type==="select"){let k=t.createElement("select"),Z=[];for(let N of c.options||[]){let z=t.createElement("option");z.value=String(N.value),z.textContent=N.label!=null?String(N.label):String(N.value),k.appendChild(z),Z.push(N.value)}B!=null&&(k.value=String(B)),k.addEventListener("change",()=>{let N=Z.find(z=>String(z)===k.value);s(c.key,N!==void 0?N:k.value)}),m.appendChild(k)}else if(c.type==="range"||c.type==="number"){let k=t.createElement("input");k.type=c.type==="range"?"range":"number",c.min!=null&&(k.min=String(c.min)),c.max!=null&&(k.max=String(c.max)),c.step!=null&&(k.step=String(c.step)),B!=null&&(k.value=String(B));let Z=()=>s(c.key,$i(k.value,B));k.addEventListener(c.type==="range"?"input":"change",Z),m.appendChild(k)}else{let k=t.createElement("input");k.type="text",c.placeholder&&(k.placeholder=c.placeholder),B!=null&&(k.value=String(B)),k.addEventListener("change",()=>s(c.key,k.value)),m.appendChild(k)}d.appendChild(m)}return d}var On=`
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    background: #111827;
    color: #e5e7eb;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ve-toolbar {
    align-items: center;
    background: #0f172a;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 12px;
    padding: 10px 14px;
    z-index: 10;
}
.ve-toolbar-logo {
    color: #8ab272;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
}
.ve-toolbar button,
.ve-toolbar a,
.ve-field-editor input,
.ve-field-editor select,
.ve-field-editor textarea {
    background: #111827;
    border: 1px solid #334155;
    border-radius: 8px;
    color: #e5e7eb;
    font: inherit;
}
.ve-toolbar-spacer {
    flex: 1;
}
.ve-toolbar-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
}
.ve-btn {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 7px 12px;
    text-decoration: none;
    transition: background 0.15s, border-color 0.15s, opacity 0.15s;
}
.ve-btn:hover { background: #1f2937; }
.ve-btn-primary {
    background: #4a7c59;
    border-color: #4a7c59;
    color: #fff;
}
.ve-btn-primary:hover { background: #3f6c4e; }
.ve-btn-danger {
    background: #7f1d1d;
    border-color: #7f1d1d;
    color: #fff;
}
.ve-btn-danger:hover { background: #991b1b; }
.ve-btn:disabled { cursor: not-allowed; opacity: 0.55; }
.ve-status {
    background: #1f2937;
    border-radius: 999px;
    font-size: 11px;
    padding: 4px 10px;
    white-space: nowrap;
}
.ve-status.is-ready { background: #17321f; color: #9ae6b4; }
.ve-status.is-loading { background: #3b2f17; color: #f6e05e; }
.ve-status.is-error { background: #3b1717; color: #feb2b2; }
.ve-mode-switch {
    display: flex;
    gap: 6px;
}
.ve-mode-btn.is-active {
    background: #4a7c59;
    border-color: #4a7c59;
    color: #fff;
}
.ve-main {
    display: flex;
    flex: 1;
    min-height: 0;
}
.ve-sidebar {
    background: #0f172a;
    border-right: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    width: 430px;
}
.ve-sidebar-header {
    align-items: flex-start;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    padding: 12px 14px;
}
.ve-sidebar-page {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.ve-sidebar-kicker {
    color: #64748b;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.ve-sidebar-title {
    font-size: 13px;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-sidebar-path {
    color: #94a3b8;
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-section-list-wrap {
    border-bottom: 1px solid #1f2937;
    max-height: 34%;
    min-height: 160px;
    overflow-y: auto;
}
.ve-section-list {
    list-style: none;
}
.ve-section-item {
    align-items: center;
    border-left: 3px solid transparent;
    border-bottom: 1px solid #1f2937;
    cursor: pointer;
    display: flex;
    gap: 10px;
    padding: 10px 14px;
}
.ve-section-item:hover { background: #111827; }
.ve-section-item.is-active {
    background: #111827;
    border-left-color: #4a7c59;
}
.ve-section-drag {
    color: #64748b;
    cursor: grab;
    font-size: 15px;
    user-select: none;
}
.ve-section-info {
    flex: 1;
    min-width: 0;
}
.ve-section-title {
    font-size: 13px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-section-meta {
    color: #94a3b8;
    display: flex;
    gap: 4px;
    margin-top: 3px;
}
.ve-layout-badge {
    background: #1e293b;
    border-radius: 999px;
    font-size: 10px;
    padding: 2px 7px;
}
.ve-section-actions {
    display: flex;
    gap: 4px;
}
.ve-icon-btn {
    align-items: center;
    background: transparent;
    border: none;
    border-radius: 6px;
    color: #94a3b8;
    cursor: pointer;
    display: inline-flex;
    height: 28px;
    justify-content: center;
    width: 28px;
}
.ve-icon-btn:hover {
    background: #1f2937;
    color: #e5e7eb;
}
.ve-field-editor {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 0;
}
.ve-editor-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 14px;
}
.ve-empty-state {
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.5;
    padding: 18px 14px;
}
.ve-field-group {
    margin-bottom: 14px;
}
.ve-field-group label {
    color: #94a3b8;
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    margin-bottom: 5px;
    text-transform: uppercase;
}
.ve-field-group input,
.ve-field-group select,
.ve-field-group textarea {
    padding: 8px 10px;
    width: 100%;
}
.ve-field-group textarea {
    min-height: 110px;
    resize: vertical;
}
.ve-form-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.ve-form-grid .ve-field-group-full {
    grid-column: 1 / -1;
}
.ve-actions-bar {
    border-top: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    padding: 12px 14px;
}
.ve-help {
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
    margin-top: 4px;
}
.ve-dirty-pill {
    background: #3b2f17;
    border-radius: 999px;
    color: #f6e05e;
    font-size: 10px;
    margin-left: 6px;
    padding: 2px 6px;
}
.ve-iframe-wrap {
    background: #fff;
    flex: 1;
    min-width: 0;
    position: relative;
}
.ve-iframe-wrap iframe {
    border: none;
    height: 100%;
    width: 100%;
}
.ve-info-card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 12px;
    padding: 12px;
}
.ve-info-card + .ve-info-card {
    margin-top: 12px;
}
.ve-info-card strong {
    display: block;
    font-size: 12px;
    margin-bottom: 4px;
}
.ve-info-card p {
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
}
.ve-media-modal {
    align-items: stretch;
    background: rgba(15, 23, 42, 0.72);
    display: none;
    inset: 0;
    justify-content: flex-end;
    position: fixed;
    z-index: 30;
}
.ve-media-modal.is-open {
    display: flex;
}
.ve-media-panel {
    background: #0f172a;
    border-left: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 460px;
    width: 100%;
}
.ve-media-header {
    align-items: center;
    border-bottom: 1px solid #1f2937;
    display: flex;
    justify-content: space-between;
    padding: 14px;
}
.ve-media-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    overflow-y: auto;
    padding: 14px;
}
.ve-preset-modal {
    align-items: stretch;
    background: rgba(15, 23, 42, 0.72);
    display: none;
    inset: 0;
    justify-content: flex-end;
    position: fixed;
    z-index: 40;
}
.ve-preset-modal.is-open {
    display: flex;
}
.ve-preset-panel {
    background: #0f172a;
    border-left: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 520px;
    width: 100%;
}
.ve-preset-header {
    align-items: center;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    padding: 14px;
}
.ve-preset-controls {
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr 160px;
    padding: 12px 14px;
}
.ve-preset-list {
    display: grid;
    gap: 10px;
    overflow-y: auto;
    padding: 0 14px 14px;
}
.ve-preset-item {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 10px;
    padding: 10px;
}
.ve-preset-item strong {
    display: block;
    font-size: 13px;
}
.ve-preset-item p {
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.45;
    margin-top: 6px;
}
.ve-preset-item .ve-inline-actions {
    margin-top: 10px;
}
.ve-add-modal {
    align-items: stretch;
    background: rgba(15, 23, 42, 0.72);
    display: none;
    inset: 0;
    justify-content: flex-end;
    position: fixed;
    z-index: 41;
}
.ve-add-modal.is-open {
    display: flex;
}
.ve-add-panel {
    background: #0f172a;
    border-left: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 560px;
    width: 100%;
}
.ve-add-header {
    align-items: center;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    padding: 14px;
}
.ve-add-controls {
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr 160px;
    padding: 12px 14px;
}
.ve-add-scroll {
    overflow-y: auto;
    padding: 0 14px 14px;
}
.ve-add-group-label {
    color: #94a3b8;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    margin: 16px 0 8px;
    text-transform: uppercase;
}
.ve-add-group-label:first-child {
    margin-top: 4px;
}
.ve-add-grid {
    display: grid;
    gap: 6px;
    grid-template-columns: 1fr 1fr;
}
.ve-add-card {
    align-items: flex-start;
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    gap: 10px;
    padding: 10px;
    transition: border-color 0.15s;
}
.ve-add-card:hover {
    border-color: #4a7c59;
}
.ve-add-icon {
    align-items: center;
    background: #1e293b;
    border-radius: 6px;
    color: #94a3b8;
    display: flex;
    flex-shrink: 0;
    font-size: 15px;
    height: 32px;
    justify-content: center;
    width: 32px;
}
.ve-add-card:hover .ve-add-icon {
    background: #4a7c59;
    color: #e5e7eb;
}
.ve-add-text {
    flex: 1;
    min-width: 0;
}
.ve-add-label {
    font-size: 12px;
    font-weight: 600;
}
.ve-add-desc {
    color: #64748b;
    font-size: 10px;
    line-height: 1.4;
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-media-card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 12px;
    cursor: pointer;
    overflow: hidden;
    padding: 0;
    text-align: left;
}
.ve-media-card img {
    display: block;
    height: 120px;
    object-fit: cover;
    width: 100%;
}
.ve-media-card-body {
    padding: 10px;
}
.ve-media-card-body strong {
    display: block;
    font-size: 12px;
    margin-bottom: 4px;
}
.ve-media-card-body span {
    color: #94a3b8;
    display: block;
    font-size: 11px;
}
.ve-busy-overlay {
    align-items: center;
    background: rgba(15, 23, 42, 0.78);
    display: none;
    inset: 0;
    justify-content: center;
    position: fixed;
    z-index: 80;
}
.ve-busy-overlay.is-visible {
    display: flex;
}
.ve-busy-dialog {
    align-items: center;
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 18px;
    box-shadow: 0 28px 70px rgba(15, 23, 42, 0.45);
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 360px;
    padding: 28px 24px;
    text-align: center;
    width: calc(100vw - 32px);
}
.ve-busy-dialog strong {
    font-size: 18px;
    font-weight: 700;
}
.ve-busy-dialog p {
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.5;
}
.ve-busy-spinner {
    animation: ve-spin 0.9s linear infinite;
    border: 5px solid rgba(148, 163, 184, 0.22);
    border-radius: 999px;
    border-top-color: #8ab272;
    height: 54px;
    width: 54px;
}
@keyframes ve-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.ve-ownership-header {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 8px 0 5px;
    border-bottom: 1px solid #1f2937;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ve-ownership-header::before {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 999px;
    flex-shrink: 0;
}
.ve-ownership-ve { color: #8ab272; }
.ve-ownership-ve::before { background: #4a7c59; }
.ve-ownership-pw { color: #f59e0b; margin-top: 10px; }
.ve-ownership-pw::before { background: #b45309; }
.ve-ownership-list {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-bottom: 4px;
}
.ve-ownership-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px 8px;
    background: #111827;
    border-radius: 6px;
    font-size: 12px;
    gap: 8px;
}
.ve-ownership-item-label { color: #e5e7eb; flex-shrink: 0; }
.ve-ownership-item-hint { color: #4b5563; font-size: 10px; text-align: right; flex: 1; }
.ve-ownership-pw-btn {
    background: #1c2030;
    border: 1px solid #334155;
    border-radius: 6px;
    color: #f59e0b;
    cursor: pointer;
    font: inherit;
    font-size: 10px;
    padding: 3px 7px;
    white-space: nowrap;
    flex-shrink: 0;
}
.ve-ownership-pw-btn:hover { background: #232b3e; border-color: #f59e0b; }
.ve-ownership-pw-btn:disabled { cursor: not-allowed; opacity: 0.45; }
.ve-config-editor {
    margin-top: 6px;
    margin-bottom: 4px;
}
.ve-config-editor .ve-field-group {
    margin-bottom: 10px;
}
.ve-collection-add {
    align-items: end;
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr auto;
    margin-bottom: 12px;
}
.ve-collection-add label {
    color: #94a3b8;
    display: block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.04em;
    margin-bottom: 4px;
    text-transform: uppercase;
}
.ve-collection-add input[type="date"] {
    background: #111827;
    border: 1px solid #334155;
    border-radius: 8px;
    color: #e5e7eb;
    font: inherit;
    padding: 7px 10px;
    width: 100%;
}
.ve-collection-add .ve-btn { white-space: nowrap; }
.ve-collection-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
`;var Gi=320,Ji=1e4,Xi=180,Yi=120,qi=["rich_text","split_media_text","split_text_media","full_width_banner","media_grid","video_embed"],Zi={rich_text:"¶",split_media_text:"◧",split_text_media:"◨",full_width_banner:"▬",media_grid:"⊞",video_embed:"▶"},Qi={page_intro:"§",media_text:"◫",cards_grid:"▦",gallery_strip:"≡",text_columns:"☰",cta_band:"▸",timeline_header:"◉",timeline_item:"◉",contact_form:"✉",membership_form:"✉",subscribe_form:"✉",visit_day_form:"✉",waiting_list_form:"✉",pricing_calculator:"⊕",events_feed:"◆",schnuppertage:"❀",saisonkalender:"❀",gallery:"▦",depot_map:"◎",geisshof_map:"◎"};function g(e){return String(e!=null?e:"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;")}function Se(e,t){let i=String(e||"").trim();if(!i)return"";if(/^https?:\/\//i.test(i))try{i=new URL(i,t).pathname||""}catch(a){}return i=i.replace(/[?#].*$/,""),i?i==="/"?"/":"/"+i.replace(/^\/+|\/+$/g,""):""}function Un(){return"draft:"+Date.now().toString(36)+"-"+Math.random().toString(36).slice(2,8)}function Lt(e,t){if(e==null)return t;try{return JSON.parse(JSON.stringify(e))}catch(i){return t}}function Nn(e={}){let t=e.win||window,i=e.doc||t.document,a=e.fetchImpl||t.fetch.bind(t),s=e.storage||t.sessionStorage;if(!i.getElementById("ve-style")){let n=i.createElement("style");n.id="ve-style",n.textContent=On,i.head.appendChild(n)}let d=nn(i),c=an(d,a),m=pn(),f=n=>{let r=i.getElementById(n);if(!r)throw new Error(`visual-editor shell: #${n} missing in skeleton`);return r},B=f("ve-iframe"),k=f("ve-status"),Z=f("ve-section-list"),N=f("ve-empty-list"),z=f("ve-current-page-title"),ce=f("ve-current-page-path"),Q=f("ve-field-editor"),Ee=f("ve-btn-add"),Qe=f("ve-btn-refresh"),T=f("ve-btn-presets"),Ie=f("ve-btn-pw"),H=f("ve-btn-save"),W=f("ve-btn-reset"),le=f("ve-mode-edit"),Ke=f("ve-mode-browse"),ue=f("ve-media-modal"),j=f("ve-media-close"),ee=f("ve-media-empty"),ae=f("ve-media-grid"),$=f("ve-preset-modal"),et=f("ve-preset-close"),Pt=f("ve-preset-search"),Re=f("ve-preset-category"),He=f("ve-preset-empty"),At=f("ve-preset-list"),We=f("ve-add-modal"),Vn=f("ve-add-close"),tt=f("ve-add-search"),je=f("ve-add-filter"),nt=f("ve-add-scroll"),Bt=f("ve-busy-overlay"),Kn=f("ve-busy-label"),y=null,F=null,D=null,h=[],G=[],pe="",w=null,E=null,Fe=!1,te={},Me="edit",it=[],Le=null,Pe=[],rt={},ke=0,Ae=!1,J=null,_e="",se=!1,Be=null,fe=null,$e=null,ne=[],ge=[],Ge=!1,ie=()=>m.getState().status==="saving",S=()=>ke>0,O=hn({listenWindow:t,origins:d.iframeOrigins,defaultTargetOrigin:new URL(d.siteUrl).origin,getTargetWindow:()=>B.contentWindow,onMessage:Si});function M(n,r){k.textContent=n,k.className="ve-status"+(r?" "+r:"")}function Dt(){$e&&clearTimeout($e),$e=setTimeout(()=>{!ie()&&!S()&&(V()?M(l.statusDraftSaved,"is-loading"):M(l.statusConnected,"is-ready"))},2600)}function we(n,r){M(n,r),Dt()}function Hn(n){let r=Se(n,d.siteUrl);return r&&d.pages.find(o=>Se(o.path,d.siteUrl)===r)||null}function ot(){if(D){z.textContent=D.label,ce.textContent=D.root+l.collectionSuffix;return}let n=y?d.pages.find(r=>r.id===y):null;if(n){z.textContent=n.title||l.pageEditableFallback,ce.textContent=n.path||"/";return}if(F){z.textContent=l.pageNotEditable,ce.textContent=F;return}z.textContent=l.pageEditableFallback,ce.textContent=l.sidebarPathPlaceholder}function ft(){Fe=!1,h=[],G=[],pe="",w=null,E=null,te={},ne=[],ge=[]}function Wn(n){let r=Se(n,d.siteUrl);if(!r)return null;for(let o of Object.keys(d.collections))if(r===o||r.indexOf(o+"/")===0)return d.collections[o];return null}function V(){return Tn(h,G)}function re(n){return n&&h.find(r=>r.id===n)||null}function Ot(){return re(w)}function Ut(){return h.filter(n=>!q(n))}function De(){if(!(!y||!F)){if(fe&&(clearTimeout(fe),fe=null),!V()){ze(s,y,F);return}Mn(s,{pageId:y,path:F,baseFingerprint:pe,sections:h,activeSectionId:w,activeField:Lt(E,null)})}}function Nt(){!y||!F||(fe&&clearTimeout(fe),fe=setTimeout(()=>{De(),!ie()&&!S()&&V()&&(M(l.statusDraftSaved,"is-loading"),Dt())},Xi))}function Oe(){Ge||(ne.push(C(h)),ne.length>Yi&&ne.shift(),ge=[])}function jn(){!ne.length||S()||(Ge=!0,ge.push(C(h)),h=C(ne.pop()),X({message:l.statusUndo}),Ge=!1)}function $n(){!ge.length||S()||(Ge=!0,ne.push(C(h)),h=C(ge.pop()),X({message:l.statusRedo}),Ge=!1)}function Je(){Bt.classList.toggle("is-visible",Ae),Bt.setAttribute("aria-hidden",Ae?"false":"true"),Kn.textContent=_e||l.busyDefault}function zt(n){if(ke+=1,_e=n||_e||l.busyDefault,ke===1&&m.dispatch({type:"busy-start",busyLabel:_e}),L(),Ae){Je(),U();return}J&&clearTimeout(J),J=setTimeout(()=>{Ae=!0,Je(),U()},Gi),U()}function lt(){if(ke>0&&(ke-=1),L(),ke>0){U();return}m.dispatch({type:"busy-end"}),J&&(clearTimeout(J),J=null),Ae=!1,_e="",Je(),U()}function gt(n,r){return zt(n),Promise.resolve().then(r).finally(()=>{lt()})}function Vt(){Be&&(clearTimeout(Be),Be=null)}function Gn(){Vt(),Be=setTimeout(()=>{!se||Fe||(se=!1,ke=0,m.dispatch({type:"busy-end"}),Ae=!1,_e="",J&&(clearTimeout(J),J=null),Je(),L(),U(l.statusPreviewFailed),M(l.statusPreviewFailed,"is-error"))},Ji)}function U(n){Fe&&O.send("save-state",{mode:Me,dirty:V(),saving:ie(),busy:S(),busyLabel:_e||"",message:n||"",selectedSectionId:w,presetTagsByComponent:rt})}function Jn(n,r){if(Fe)for(let o of In(r,n))O.send("section-update",{sectionId:n.id,field:o.field,value:o.value})}function L(){let n=S(),r=ie(),o=V();Ee.disabled=!y||r||n,Ie.disabled=!y||n,Qe.disabled=!y&&!F||n,T.disabled=n,H.disabled=!o||r||n,W.disabled=!o||r||n,le.disabled=n,Ke.disabled=n,H.textContent=r?l.btnPublishing:l.btnPublish,le.classList.toggle("is-active",Me==="edit"),Ke.classList.toggle("is-active",Me==="browse")}function Xn(){return V()?t.confirm(l.confirmDiscard):!0}function me(n){return!V()||!{[l.actionPageSwitch]:!0,[l.actionReload]:!0}[n]?!1:!t.confirm(l.confirmDirtyAction(n))}function mt(n){return String(n||"").trim().toLowerCase()}function Xe(n){let r=mt(n);if(!r)return null;for(let o of d.componentRegistry){if(mt(o.key)===r)return o;for(let u of o.aliases||[])if(mt(u)===r)return o}return null}function Yn(n){let r=String(n||"").trim();if(!r)return"";let o=Xe(r);return o?r===o.key?`${o.label} (${o.key})`:`${o.label} (${r})`:r}function qn(n){let r=String(n.title||"").trim();if(r)return r;if(n.component){let o=Xe(n.component);return o?o.label:n.component}return l.layoutLabels[n.layout||""]||n.layout||l.untitledSection}function Zn(n){if(q(n))return"hero";if(n.component){let r=Xe(n.component);return(r==null?void 0:r.key)||String(n.component)}return String(n.layout||"")}function Kt(n){if(S()||!y)return;if(V()){t.alert(l.alertPwFocusNeedsCleanDraft);return}let r=n.sectionId?re(n.sectionId):Ot(),o=An({pageEditUrl:d.pageEditUrl,visualEditorUrl:d.visualEditorUrl,pageId:y,path:F||"",section:r,request:n,focusFields:d.focusFields,resolveComponent:u=>{let p=Xe(u);return p?{key:p.key,cmsFields:p.cmsFields}:null}});if("url"in o){t.open(o.url,"_blank","noopener");return}if(o.error==="publish_first"){t.alert(l.alertPwFocusPublishFirst);return}t.alert(l.alertPwFocusUnavailable)}function Qn(n,r){D=n,y=null,F=Se(r,d.siteUrl),h=[],G=[],w=null,E=null,te={},ot(),be(),L(),M(l.collectionStatus(n.label),"is-ready"),ei()}function ei(){var o;if(!D)return;let n=D,r=new Date().toISOString().slice(0,10);Q.innerHTML='<div class="ve-info-card" style="margin-bottom:10px"><strong>'+g(n.label)+"</strong><p>"+g(l.collectionDescription(n.root))+'</p></div><div class="ve-collection-add"><div><label for="ve-col-date">'+g(l.collectionDateLabel)+'</label><input type="date" id="ve-col-date" value="'+g(r)+'"></div><button class="ve-btn ve-btn-primary" id="ve-col-add" type="button">'+g(n.addLabel)+'</button></div><div class="ve-ownership-header ve-ownership-pw">'+g(l.collectionEntriesHeader)+'</div><div class="ve-collection-list" id="ve-col-list"><div class="ve-empty-state">'+g(l.collectionLoading)+"</div></div>",(o=i.getElementById("ve-col-add"))==null||o.addEventListener("click",ii),Ht()}function ti(n){let o=String(n.status||n._status||"")==="past"?l.collectionBadgePast:l.collectionBadgeUpcoming,u=[String(n.dateLabel||""),o].filter(Boolean).join(" · ");return'<div class="ve-ownership-item"><span class="ve-ownership-item-label">'+g(n.title||l.collectionEntryUntitled)+'<br><span style="color:#64748b;font-size:10px">'+g(u)+'</span></span><button class="ve-ownership-pw-btn" type="button" data-edit-id="'+g(String(n.id||""))+'">'+g(l.openInPw)+"</button></div>"}function Ht(){if(!D)return;let n=i.getElementById("ve-col-list");n&&c.fetchCollectionEntries(D.listEndpoint).then(r=>{let o=sn(r);if(!o.length){n.innerHTML='<div class="ve-empty-state">'+g(l.collectionEmpty)+"</div>";return}n.innerHTML=o.map(ti).join("");for(let u of Array.from(n.querySelectorAll("[data-edit-id]")))u.addEventListener("click",()=>ni(u.getAttribute("data-edit-id")))}).catch(()=>{n.innerHTML='<div class="ve-empty-state">'+g(l.collectionLoadFailed)+"</div>"})}function ni(n){n&&t.open(d.pageEditUrl+"?id="+encodeURIComponent(n),"_blank","noopener")}function ii(){if(S()||!D)return;let n=i.getElementById("ve-col-date"),r=n?n.value:"";gt(l.busyCreatingEntry,()=>c.createCollectionEntry(D.type,r).then(o=>{we(l.statusEntryCreated,"is-ready"),o&&typeof o.editUrl=="string"&&o.editUrl&&t.open(o.editUrl,"_blank","noopener"),Ht()}).catch(o=>{M(o instanceof Error&&o.message||l.errorEntryCreateFailed,"is-error")}))}function ri(n){let r=Wn(n);if(r)return D&&D.root===r.root||(De(),Qn(r,n)),null;let o=Hn(n);return o?(!D&&y===o.id&&F===o.path||(D=null,De(),y=o.id,F=o.path,ft(),ot(),be(),Ce(),L(),M(l.statusLoadingSections,"is-loading")),o):(D=null,De(),y=null,F=Se(n,d.siteUrl)||n,ft(),ot(),be(),Ce(),L(),M(l.pageUnavailable,"is-error"),null)}function oi(n={}){if(!y||!F)return Promise.resolve();let r=F;return n.keepStatus||M(l.statusLoadingSections,"is-loading"),gt(n.busyLabel||l.busyLoadingSections,async()=>{try{let o=await c.fetchSections(r),u=C(Array.isArray(o.sections)?o.sections:[]);r==="/"&&o.hero&&u.unshift(pt(o.hero,y)),G=C(u),pe=String(o.fingerprint||"");let p=Ln(s,{pageId:y,path:r,sections:u,fingerprint:pe});if(h=p.sections,p.restored){p.activeSectionId&&h.some(I=>I.id===p.activeSectionId)&&(w=p.activeSectionId);let _=p.activeField;_&&_.sectionId===w&&(E=_)}w&&!re(w)&&(w=null,E=null),ne=[],ge=[],X({persist:!1,message:p.message||""}),n.keepStatus?p.message&&we(p.message,"is-loading"):M(p.message||(V()?l.statusDraftSaved:l.statusConnected),V()?"is-loading":"is-ready")}catch(o){throw M(o instanceof Error&&o.message||l.errorLoadFailed,"is-error"),o}})}function Wt(n){ft(),be(),Ce(),L(),M(l.statusLoadingPreview,"is-loading"),se=!0,zt(l.busyLoadingPreview),Gn();let r=d.siteUrl+(Se(n,d.siteUrl)||"/");r+=(r.indexOf("?")===-1?"?":"&")+"_visual=1",d.draftSecret&&(r+="&draft_secret="+encodeURIComponent(d.draftSecret)),B.src=r}function li(){w&&!re(w)&&(w=null,E=null),E&&w!==E.sectionId&&(E=null)}function X(n={}){te=Et(h,G),li(),be(),Ce(),L(),Fe&&(O.send("sections-replace",{sections:h}),O.send("section-highlight",{sectionId:w}),E&&w===E.sectionId?O.send("field-highlight",E):(E=null,O.send("field-reset",{}))),U(n.message||""),n.persist!==!1&&Nt()}let ai={layout:!0,theme:!0,bgColor:!0,imageOverlay:!0,component:!0,config:!0,mediaItems:!0,videoUrl:!0,videoTitle:!0};function jt(n){if(S())return;let r=re(n.sectionId);if(!r)return;(n.__commit||ai[n.field])&&Oe();let o=En(r,n);o!==r&&(h=h.map(u=>u.id===r.id?o:u),m.dispatch({type:"edit"}),Jn(o,n.field),te=Et(h,G),be(),Ce(),Nt(),U(),L())}function bt(n,r={}){re(n)&&(w=n,m.dispatch({type:"select-section",sectionId:n}),r.clearField!==!1&&(E=null),be(),Ce(),L(),O.send("section-highlight",{sectionId:n}),r.clearField!==!1?O.send("field-reset",{}):E&&O.send("field-highlight",E),r.scroll!==!1&&O.send("section-scroll",{sectionId:n}),U())}function $t(n,r={}){!n||!n.sectionId||!re(n.sectionId)||(w=n.sectionId,m.dispatch({type:"select-section",sectionId:n.sectionId}),E={...n},be(),Ce(),L(),O.send("section-highlight",{sectionId:n.sectionId}),O.send("field-highlight",E),r.scroll!==!1&&O.send("section-scroll",{sectionId:n.sectionId}),U())}function be(){Z.innerHTML="",N.textContent=y?l.emptyNoSections:l.emptyNoPage,N.style.display=h.length?"none":"block",h.forEach(n=>{let r=q(n),o=i.createElement("li");o.className="ve-section-item"+(n.id===w?" is-active":""),o.draggable=!r;let u=i.createElement("span");u.className="ve-section-drag",u.textContent=r?"★":"⠿";let p=i.createElement("div");p.className="ve-section-info";let _=i.createElement("div");_.className="ve-section-title",_.textContent=qn(n),p.appendChild(_);let I=i.createElement("div");I.className="ve-section-meta";let b=i.createElement("span");b.className="ve-layout-badge",b.textContent=l.layoutLabels[n.layout||""]||n.layout||l.sectionFallbackBadge,I.appendChild(b);let ye=Zn(n);if(ye){let x=i.createElement("span");x.className="ve-layout-badge",x.textContent="PW: "+ye,I.appendChild(x)}if(te[n.id]){let x=i.createElement("span");x.className="ve-dirty-pill",x.textContent=l.dirtyPill,I.appendChild(x)}p.appendChild(I);let oe=i.createElement("div");if(oe.className="ve-section-actions",!r){let x=i.createElement("button");x.className="ve-icon-btn",x.type="button",x.title=l.duplicateTitle,x.textContent="⧉",x.addEventListener("click",he=>{he.stopPropagation(),!S()&&(me(l.actionCopy)||Zt(n))});let A=i.createElement("button");A.className="ve-icon-btn",A.type="button",A.title=l.deleteTitle,A.textContent="✕",A.addEventListener("click",he=>{he.stopPropagation(),!S()&&(me(l.actionDelete)||t.confirm(l.confirmDeleteSection(n.title||""))&&qt(n))}),oe.appendChild(x),oe.appendChild(A)}o.appendChild(u),o.appendChild(p),o.appendChild(oe),o.addEventListener("click",()=>{S()||bt(n.id)}),o.addEventListener("dragstart",x=>{var A;if(r||S()||me(l.actionSort)){x.preventDefault();return}(A=x.dataTransfer)==null||A.setData("text/plain",n.id),o.style.opacity="0.5"}),o.addEventListener("dragend",()=>{o.style.opacity="1",o.style.borderTop=""}),o.addEventListener("dragover",x=>{x.preventDefault(),o.style.borderTop="2px solid #4a7c59"}),o.addEventListener("dragleave",()=>{o.style.borderTop=""}),o.addEventListener("drop",x=>{var he;if(x.preventDefault(),o.style.borderTop="",r)return;let A=(he=x.dataTransfer)==null?void 0:he.getData("text/plain");!A||A===n.id||Jt(A,n.id)}),Z.appendChild(o)})}function Y([n,r]){return'<div class="ve-ownership-item"><span class="ve-ownership-item-label">'+g(n)+'</span><span class="ve-ownership-item-hint">'+g(r)+"</span></div>"}function at(n,r){return'<div class="ve-ownership-item"><span class="ve-ownership-item-label">'+g(n)+'</span><button class="ve-ownership-pw-btn" type="button" data-pw-focus="'+g(r)+'">'+g(l.openInPw)+"</button></div>"}function Ce(){if(D){L();return}let n=Ot(),r=y?d.pages.find(x=>x.id===y):null,o=Object.keys(te).length,u=V();if(!y||!r){Q.innerHTML='<div class="ve-empty-state">'+g(l.emptyNoPage)+"</div>",L();return}if(!n){Q.innerHTML='<div class="ve-info-card"><strong>'+g(l.infoCardPage)+"</strong><p>"+g(r.title)+" ("+g(r.path)+')</p></div><div class="ve-info-card"><strong>'+g(l.infoCardMode)+"</strong><p>"+g(Me==="edit"?l.modeEditDescription:l.modeBrowseDescription)+'</p></div><div class="ve-info-card"><strong>'+g(l.infoCardStatus)+"</strong><p>"+g(u?l.draftOpenDescription:l.draftNoneDescription)+"</p></div>",L();return}let p=q(n),I=p||["split_media_text","split_text_media","full_width_banner","media_grid"].indexOf(n.layout||"")!==-1,b="";if(b+='<div class="ve-info-card" style="margin-bottom:10px"><strong style="display:flex;justify-content:space-between;align-items:center">'+g(n.title||l.untitledSection)+(te[n.id]?'<span class="ve-dirty-pill">'+g(l.dirtyPill)+"</span>":"")+'</strong><p style="color:#64748b;font-size:11px;margin-top:2px">'+g(l.layoutLabels[n.layout||""]||n.layout||l.sectionFallbackBadge)+(n.component?" · "+g(Yn(n.component)):"")+"</p></div>",b+='<div class="ve-ownership-header ve-ownership-ve">'+g(l.ownershipVe)+"</div>",b+='<div class="ve-ownership-list">',p)for(let x of l.veRowHero)b+=Y(x);else b+=Y(l.veRowTitle),b+=Y(l.veRowEyebrow),b+=Y(l.veRowText),b+=Y(l.veRowLayoutTheme),b+=Y(l.veRowBgOverlay),b+=Y(l.veRowButtons),I&&(b+=Y(l.veRowMedia),b+=Y(l.veRowMediaMeta)),n.layout==="video_embed"&&(b+=Y(l.veRowVideo)),n.component&&(b+=Y(l.veRowComponentConfig));b+="</div>";let ye=n.component?Xe(n.component):null,oe=ye?Bn(ye.configSchema):[];if(oe.length&&(b+='<div class="ve-ownership-header ve-ownership-ve">'+g(l.configEditorHeader)+"</div>",b+='<div id="ve-config-editor-slot"></div>'),b+='<div class="ve-ownership-header ve-ownership-pw">'+g(l.ownershipPw)+"</div>",b+='<div class="ve-ownership-list">',p?(b+=at(l.pwRowHeroImage,"media"),b+=at(l.pwRowHeroAll,"")):(I&&(b+=at(l.pwRowImages,"media")),b+=at(l.pwRowAllFields,"")),b+="</div>",u&&o&&(b+='<div class="ve-info-card" style="margin-top:10px"><strong>'+g(l.infoCardDraft)+"</strong><p>"+g(l.draftDirtyCount(o))+"</p></div>"),Q.innerHTML=b,oe.length){let x=i.getElementById("ve-config-editor-slot");x&&x.appendChild(Dn({doc:i,schema:oe,config:n.config||{},onChange:(A,he)=>{jt({sectionId:n.id,field:"config",configKey:A,value:he})}}))}for(let x of Array.from(Q.querySelectorAll("[data-pw-focus]"))){let A=x.getAttribute("data-pw-focus")||"";x.addEventListener("click",()=>{Kt(A?{field:A}:{})})}L()}function Gt(){if(ie()||!V()||S()||!y||!F)return;let n=C(G),r=F;m.dispatch({type:"edit"}),m.dispatch({type:"publish-start",busyLabel:l.busyPublishing}),L(),M(l.btnPublishing,"is-loading"),U(),gt(l.busyPublishing,()=>c.publish({pageId:y,path:r,baseFingerprint:pe,sections:C(h)})).then(o=>{let u=C(Array.isArray(o.sections)?o.sections:[]);r==="/"&&o.hero&&u.unshift(pt(o.hero,y)),G=C(u),pe=String(o.fingerprint||""),h=C(u),ne=[],ge=[],te={},ze(s,y,r);let p=o.revalidated!==!1;m.dispatch({type:"publish-success",revalidated:p}),X({persist:!1,message:l.statusPublished});let _=dn(o);M(_.text,_.cls),O.send("save-result",{success:!0,revalidated:p})}).catch(o=>{let u=o instanceof qe?o:null,p=o instanceof Error&&o.message||l.errorPublishFailed;m.dispatch({type:"publish-failure",error:p});let _=!1,I=u==null?void 0:u.data;if(I&&(Array.isArray(I.sections)||I.hero)){let b=C(Array.isArray(I.sections)?I.sections:[]);if(r==="/"&&I.hero&&b.unshift(pt(I.hero,y)),I.fingerprint){let ye=Pn(n,b,h,{keepLocalField:(oe,x)=>t.confirm(l.confirmFieldConflict(oe,x)),keepLocalOrder:()=>t.confirm(l.confirmOrderConflict)});G=C(b),pe=String(I.fingerprint||""),h=C(ye.mergedSections),te={},X({message:ye.conflicts.length?l.statusConflictsResolved:l.statusServerChangesAdopted}),_=!0}else G=C(b),pe=String(I.fingerprint||"")}_?M(l.statusConflictsRetry,"is-loading"):M(p,"is-error"),O.send("save-result",{success:!1,error:p})}).finally(()=>{L(),U()})}function si(){S()||Xn()&&(y&&F&&ze(s,y,F),h=C(G),ne=[],ge=[],te={},E=null,w&&!re(w)&&(w=null),m.dispatch({type:"discard"}),X({persist:!1,message:l.statusDraftDiscarded}),M(l.statusDraftDiscarded,"is-ready"))}function Jt(n,r){if(!y||ie()||S()||me(l.actionSort))return;Oe();let o=h.filter(b=>q(b)),u=Ut().slice(),p=u.findIndex(b=>b.id===n),_=u.findIndex(b=>b.id===r);if(p===-1||_===-1||p===_)return;let[I]=u.splice(p,1);u.splice(_,0,I),h=o.concat(u),m.dispatch({type:"edit"}),X(),we(l.statusOrderUpdated,"is-loading")}function Xt(n,r){if(!y||ie()||S()||me(l.actionMove))return;let o=Ut(),u=o.findIndex(_=>_.id===n);if(u===-1)return;let p=u+r;p<0||p>=o.length||Jt(o[u].id,o[p].id)}function Yt(n,r,o){if(!y||ie()||S())return;Oe();let u=Ve({id:Un(),title:o||l.newSectionTitle,text:"<p></p>",layout:"rich_text",theme:"default",buttons:[],...n});u&&(h=C(h),h.push(u),w=u.id,E=null,m.dispatch({type:"edit"}),X(),we(r,"is-loading"))}function qt(n){!y||!n||q(n)||ie()||S()||(Oe(),h=h.filter(r=>r.id!==n.id),w===n.id&&(w=null,E=null),m.dispatch({type:"edit"}),X(),we(l.statusSectionDeleted,"is-loading"))}function Zt(n){if(!n||q(n)||!y||ie()||S()||me(l.actionDuplicate))return;Oe();let r=C([n])[0];if(!r)return;let o=h.findIndex(u=>u.id===n.id);r.id=Un(),delete r.pwId,r.title=(n.title||l.newSectionTitle)+l.copySuffix,h=C(h),o===-1?h.push(r):h.splice(o+1,0,r),w=r.id,E=null,m.dispatch({type:"edit"}),X(),we(l.statusSectionDuplicated,"is-loading")}function di(n,r){let o=re(n);if(!(!o||q(o)))switch(r){case"delete":if(!t.confirm(l.confirmDeleteSection(o.title||"")))return;qt(o);break;case"move-up":Xt(n,-1);break;case"move-down":Xt(n,1);break;case"duplicate":Zt(o);break}}function ci(n){!n||!n.sectionId||S()||(Le={sectionId:n.sectionId,targetField:n.targetField||(n.sectionId==="__hero__"?"hero_image":"section_image")},it=[],ae.innerHTML="",ee.textContent=l.mediaLoading,ee.style.display="block",ue.classList.add("is-open"),c.fetchMediaFiles().then(r=>{it=r,ui()}).catch(r=>{ae.innerHTML="",ee.textContent=r instanceof Error&&r.message||l.mediaLoadFailed,ee.style.display="block"}))}function st(){Le=null,ue.classList.remove("is-open")}function ui(){if(ae.innerHTML="",!it.length){ee.textContent=l.mediaEmpty,ee.style.display="block";return}ee.style.display="none";for(let n of it){let r=i.createElement("button");r.type="button",r.className="ve-media-card";let o=String(n.assetTitle||n.fileName||l.mediaFallbackName);r.innerHTML='<img src="'+g(String(n.url||""))+'" alt="'+g(o)+'"><div class="ve-media-card-body"><strong>'+g(o)+"</strong><span>"+g(String(n.fileName||""))+"</span></div>",r.addEventListener("click",()=>pi(n)),ae.appendChild(r)}}function pi(n){if(S())return;let r=Le?re(Le.sectionId):null;if(!r||!Le)return;Oe();let o=Le.targetField||(q(r)?"hero_image":"section_image"),u=n,p=o==="section_images"?Fn(r,u,o):Rn(r,u,o);h=h.map(_=>_.id===r.id?p:_),m.dispatch({type:"edit"}),st(),X(),E?$t(E,{scroll:!1}):r.id&&bt(r.id,{scroll:!1}),we(l.statusMediaSelected,"is-loading")}function fi(){var n;rt={};for(let r of Pe){let o=(n=r==null?void 0:r.payload)==null?void 0:n.component;if(!o)continue;let u=String(o),p=rt[u]=rt[u]||[];r.category&&!p.includes(String(r.category))&&p.push(String(r.category)),r.name&&!p.includes(String(r.name))&&p.push(String(r.name))}}function gi(){let n=Re.value||"",r=new Set;for(let o of Pe)o!=null&&o.category&&r.add(String(o.category));Re.innerHTML='<option value="">'+g(l.presetAllCategories)+"</option>";for(let o of Array.from(r).sort()){let u=i.createElement("option");u.value=o,u.textContent=o,Re.appendChild(u)}Re.value=n}function mi(){let n=String(Pt.value||"").trim().toLowerCase(),r=String(Re.value||"");return Pe.filter(o=>{var p;return r&&o.category!==r?!1:n?[o.name,o.description,o.category,(p=o.payload)==null?void 0:p.component].filter(Boolean).join(" ").toLowerCase().indexOf(n)!==-1:!0})}function Ye(){At.innerHTML="";let n=mi();if(!n.length){He.textContent=l.presetEmpty,He.style.display="block";return}He.style.display="none";for(let r of n){let o=i.createElement("div");o.className="ve-preset-item",o.innerHTML="<strong>"+g(r.name||l.presetFallbackName)+"</strong>"+(r.category?'<span class="ve-layout-badge">'+g(r.category)+"</span>":"")+"<p>"+g(r.description||"")+"</p>";let u=i.createElement("div");u.className="ve-inline-actions";let p=i.createElement("button");p.type="button",p.className="ve-btn ve-btn-primary",p.textContent=l.presetInsert,p.addEventListener("click",()=>bi(r)),u.appendChild(p),o.appendChild(u),At.appendChild(o)}}function Qt(){return c.fetchPresets().then(n=>{Pe=n,fi(),gi(),Ye(),U()}).catch(n=>{Pe=[],He.textContent=n instanceof Error&&n.message||l.presetLoadFailed,He.style.display="block"})}function bi(n){!n||!n.payload||S()||!y||(dt(),Yt(Lt(n.payload,{})||{},l.statusPresetInserted,n.name))}function yi(){S()||(Pe.length?Ye():Qt().then(()=>Ye()),$.classList.add("is-open"))}function dt(){$.classList.remove("is-open")}let en=(()=>{let n=[];for(let r of qi)n.push({id:"layout-"+r,category:l.addCategoryBase,label:l.layoutLabels[r]||r,description:l.coreLayoutDescriptions[r]||"",icon:Zi[r]||"◆",payload:{layout:r}});for(let r of d.componentRegistry)n.push({id:"component-"+r.key,category:l.componentCategories[r.key]||l.addCategoryOther,label:r.label||r.key,description:String(r.notes||""),icon:Qi[r.key]||"◆",payload:{layout:"component",component:r.key,config:r.defaultConfig||{}}});return n.sort((r,o)=>{let u=l.addCategoryOrder.indexOf(r.category),p=l.addCategoryOrder.indexOf(o.category);return u===-1&&(u=99),p===-1&&(p=99),u-p}),n})();function hi(){let n=new Set(en.map(r=>r.category));je.innerHTML='<option value="">'+g(l.addAllFilter)+"</option>";for(let r of Array.from(n)){let o=i.createElement("option");o.value=r,o.textContent=r,je.appendChild(o)}}function vi(){let n=String(tt.value||"").trim().toLowerCase(),r=String(je.value||"");return en.filter(o=>r&&o.category!==r?!1:n?[o.label,o.description,o.category].join(" ").toLowerCase().indexOf(n)!==-1:!0)}function yt(){nt.innerHTML="";let n=vi();if(!n.length){nt.innerHTML='<div class="ve-empty-state">'+g(l.addEmpty)+"</div>";return}let r="",o=null;for(let u of n){if(u.category!==r){r=u.category;let _=i.createElement("div");_.className="ve-add-group-label",_.textContent=r,nt.appendChild(_),o=i.createElement("div"),o.className="ve-add-grid",nt.appendChild(o)}let p=i.createElement("div");p.className="ve-add-card",p.innerHTML='<div class="ve-add-icon">'+g(u.icon)+'</div><div class="ve-add-text"><div class="ve-add-label">'+g(u.label)+'</div><div class="ve-add-desc">'+g(u.description)+"</div></div>",p.addEventListener("click",()=>{ct(),Yt(Lt(u.payload,{}),g(u.label)+l.statusTypeAddedSuffix)}),o.appendChild(p)}}function xi(){S()||!y||me(l.actionAdd)||(hi(),tt.value="",je.value="",yt(),We.classList.add("is-open"),tt.focus())}function ct(){We.classList.remove("is-open")}function Si(n){if(n.type==="ready"){let r=Se(n.path,d.siteUrl),o=null;if(r&&(m.dispatch({type:"iframe-ready",path:r}),o=ri(r)),Fe=!0,Vt(),U(),r&&!o){se&&(se=!1,lt());return}M(l.statusConnected,"is-ready"),y&&F?oi({busyLabel:l.busyLoadingSections}).catch(()=>{}).finally(()=>{se&&(se=!1,lt())}):se&&(se=!1,lt());return}if(!S())switch(n.type){case"section-click":bt(n.sectionId,{scroll:!1});break;case"field-select":$t(n,{scroll:!1});break;case"field-change":case"field-commit":jt({sectionId:n.sectionId,field:n.field,value:n.value,buttonIndex:n.buttonIndex,configKey:n.configKey,__commit:n.type==="field-commit"});break;case"media-request":ci(n);break;case"open-processwire":Kt(n);break;case"section-action":di(n.sectionId,n.action);break}}let tn=[];function P(n,r,o){n.addEventListener(r,o),tn.push(()=>n.removeEventListener(r,o))}return P(Qe,"click",()=>{S()||!F&&!y||me(l.actionReload)||(De(),Wt(F||"/"))}),P(T,"click",()=>yi()),P(Ie,"click",()=>{S()||!y||t.open(d.pageEditUrl+"?id="+y,"_blank")}),P(Ee,"click",()=>xi()),P(H,"click",()=>Gt()),P(W,"click",()=>si()),P(le,"click",()=>{S()||(Me="edit",L(),U())}),P(Ke,"click",()=>{S()||(Me="browse",L(),U())}),P(j,"click",()=>st()),P(ue,"click",n=>{S()||n.target===ue&&st()}),P(et,"click",()=>dt()),P($,"click",n=>{S()||n.target===$&&dt()}),P(Pt,"input",()=>Ye()),P(Re,"change",()=>Ye()),P(Vn,"click",()=>ct()),P(We,"click",n=>{n.target===We&&ct()}),P(tt,"input",()=>yt()),P(je,"change",()=>yt()),P(t,"keydown",n=>{if(S())return;let r=!!(n.metaKey||n.ctrlKey);if(r&&n.key.toLowerCase()==="s"){n.preventDefault(),Gt();return}if(r&&n.key.toLowerCase()==="z"){n.preventDefault(),n.shiftKey?$n():jn();return}n.key==="Escape"&&(We.classList.contains("is-open")&&ct(),ue.classList.contains("is-open")&&st(),$.classList.contains("is-open")&&dt())}),P(t,"beforeunload",n=>{De(),V()&&(n.preventDefault(),n.returnValue="")}),M(l.statusDisconnected),Je(),ot(),L(),Qt(),(()=>{let n=new URLSearchParams(t.location.search||""),r=Se(n.get("path")||"",d.siteUrl)||"/";Wt(r)})(),{destroy(){O.destroy();for(let n of tn)n();J&&clearTimeout(J),Be&&clearTimeout(Be),fe&&clearTimeout(fe),$e&&clearTimeout($e)}}}function zn(){Nn()}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",zn):zn();})();
