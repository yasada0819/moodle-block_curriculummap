<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Japanese language strings for block_curriculummap.
 *
 * @package    block_curriculummap
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'カリキュラムマップ';
$string['curriculummap:addinstance']   = 'カリキュラムマップブロックを追加する';
$string['curriculummap:myaddinstance'] = 'ダッシュボードにカリキュラムマップブロックを追加する';
$string['curriculummap:view']          = 'カリキュラムマップを表示する';
$string['curriculummap:manage']        = 'カリキュラムマップの表示モードとフレームワーク紐づけを設定する';

$string['privacy:metadata'] = 'このブロックはコース・コンピテンシーの構造データのみを集計して表示し、個人データは保持しません。';

$string['displaymode']       = '表示モード';
$string['displaymode_link']  = 'コンパクト表示+別ページへのリンク(デフォルト)';
$string['displaymode_modal'] = 'コンパクト表示+ポップアップ(モーダル)で開く';
$string['displaymode_full']  = 'フル可視化をそのまま表示';
$string['displaymode_managedonly'] = 'この設定はマネージャまたは管理者のみ変更できます。';
$string['configtitle'] = 'ブロックタイトル(任意・カスタマイズ用)';

$string['openfullview']  = 'カリキュラムマップを開く';
$string['viewpagetitle'] = 'カリキュラムマップ';
$string['nodataconfigured'] = 'まだコンピテンシーフレームワークが紐づけられていません。ブロックのグローバル設定でマネージャに設定を依頼してください。';
$string['rendererpending'] = 'クロス集計の可視化エンジンはまだこのプラグインに移植されていません(骨組み段階)。データ取得・権限まわりの配管は動いています。描画部分は次の増分で対応予定です。';

$string['settings_frameworksheading'] = 'フレームワーク紐づけ';
$string['settings_frameworksheading_desc'] = 'DP軸・コアカリ軸として使うMoodleコンピテンシーフレームワークを指定します。空欄の軸は表示されません。';
$string['settings_dpframeworkidnumber'] = 'DPフレームワークのidnumber';
$string['settings_dpframeworkidnumber_desc'] = 'ディプロマ・ポリシー(DP)を表すコンピテンシーフレームワークのidnumber。';
$string['settings_coreframeworkidnumber'] = 'コアカリフレームワークのidnumber';
$string['settings_coreframeworkidnumber_desc'] = '医学教育モデル・コア・カリキュラムを表すコンピテンシーフレームワークのidnumber。';
$string['settings_milestoneframeworkidnumber'] = 'マイルストーンフレームワークのidnumber';

// ブロックインスタンス単位の上書き対応(manage.php)。
$string['manageinstancenote'] = 'ここでの設定は、このブロックインスタンス1つだけに適用されます。「サイト共通の設定を使う」のままの軸は、サイト全体の設定がそのまま使われます。';
$string['manageinstanceheading'] = '「{$a}」専用の設定';
$string['datasource_inherit'] = 'サイト共通の設定を使う';
$string['category_inherit'] = '科目カテゴリ抽出もサイト共通の設定を使う';
$string['csvimportintroinstance'] = 'CSVをアップロードすると、このブロックインスタンス専用の軸データ(dp/core/milestoneのいずれか)を丸ごと置き換えます。サイト共通データや他のブロックインスタンスには影響しません。';

// 軸ごとのデータソース切替(manage.php / settings.php)。
$string['axisheading_dp'] = 'DP(ディプロマ・ポリシー)';
$string['axisheading_core'] = 'コアカリ';
$string['axisheading_milestone'] = 'マイルストーン';
$string['datasource'] = 'データソース';
$string['datasource_competency'] = 'Moodleコンピテンシー(リアルタイム)';
$string['datasource_csv'] = 'CSVアップロード';
$string['managecsvnote'] = 'この軸は、下の「CSVデータを取り込む」からアップロードしたCSVデータを読み込みます。';

// カテゴリ(course.idnumberの部分文字列)設定。
$string['settings_categoryheading'] = '科目カテゴリの抽出設定';
$string['settings_categoryheading_desc'] = '科目カテゴリは各コースのidnumberの部分文字列から取得します(例: 「2026_M_L3XXXX」でoffset=7, length=2 → 「L3」)。フレームワークやCSVは使いません。';
$string['settings_category_idnumber_offset'] = '切り出し開始位置(0始まり)';
$string['settings_category_idnumber_length'] = '切り出す文字数';

// manage.php: 保存済みCSVデータのプレビュー。
$string['csvdatapreview'] = '現在保存されているCSVデータ';
$string['gotocsvimport'] = 'CSVデータを取り込む';
$string['csvrowcount'] = '{$a}件保存済み';
$string['csvnorowsyet'] = 'この軸にはまだCSVデータがありません。';
$string['csvcol_course'] = 'コースidnumber';
$string['csvcol_coursename'] = 'コース名';
$string['csvcol_itemidnumber'] = '項目idnumber';
$string['csvcol_itemlabel'] = '項目ラベル';
$string['csvcol_parentlabel'] = 'グループ/親ラベル';
$string['csvpreviewtruncated'] = '全{$a}件中、最初の50件のみ表示しています。';
$string['csvpreviewshowmore'] = '残り{$a}件を表示';

// csv_import.php。
$string['csvimporttitle'] = 'CSVデータの取り込み';
$string['csvimportintro'] = 'CSVをアップロードすると、その軸(dp/core/milestoneのいずれか)の保存済みデータを丸ごと置き換えます。確定するまでは反映されません。course_idnumberが既存のMoodleコースと一致しなくても問題ありません。その場合は「仮想科目」として扱われ、course_name(未入力ならidnumberそのもの)が表示名になります。';
$string['csvaxisid'] = 'どの軸のデータですか?';
$string['csvformatdesc'] = '列: course_idnumber, item_idnumber, item_label, parent_label, course_name(parent_labelとcourse_nameは任意。parent_labelはマイルストーンのような階層のない軸では空欄でOK。course_nameはcourse_idnumberが実在コースと一致しない場合のみ使われます)。1科目に複数項目がある場合は複数行に分けてください。';
$string['csvfile'] = 'CSVファイル';
$string['csvuploadpreview'] = 'プレビュー';
$string['csvpreviewheading'] = 'プレビュー';
$string['csvpreviewsummary'] = '全{$a->total}件: 既存コースに一致{$a->matched}件、仮想科目として取込{$a->virtual}件、必須項目欠落(ブロック){$a->missing_fields}件。';
$string['csvcol_line'] = '行';
$string['csvcol_status'] = 'ステータス';
$string['csvstatus_matched'] = '既存コースに一致';
$string['csvstatus_virtual'] = 'コース未一致(仮想科目)';
$string['csvstatus_missing_fields'] = '必須項目が空です';
$string['csvzeroimportablewarning'] = 'すべての行で必須項目が欠落しているため、今回は何も取り込まれません。ただし確定すれば、この軸の既存データをクリアすることはできます。';
$string['csvconfirmimport'] = '取り込みを確定';
$string['csvimportdone'] = '取り込み完了: {$a->inserted}件取込、{$a->skipped}件スキップ。';

// 可視化(amd/src/curriculummap.js + templates/full.mustache)。
$string['viz_rowaxis'] = '行軸';
$string['viz_colaxis'] = '列軸';
$string['viz_sortorder'] = 'ソート順';
$string['viz_asc'] = '昇順';
$string['viz_desc'] = '降順';
$string['viz_swapaxes'] = '⇄ 行列入替';
$string['viz_mode'] = '集計モード(合計行/列)';
$string['viz_mode_total'] = '延べ';
$string['viz_mode_unique'] = 'ユニーク';
$string['viz_celldisplay'] = 'セル表示';
$string['viz_celldisplay_number'] = '数値';
$string['viz_celldisplay_segments'] = '分割';
$string['viz_reset'] = '選択をリセット';
$string['viz_addfilteraxis'] = 'フィルタ軸を追加';
$string['viz_addfilterbtn'] = '+ 追加';
$string['viz_note'] = '集計モードは行/列の「合計」の数え方(同じ科目が複数値にまたがる場合に重複カウントするか否か)にのみ反映され、セル自体には影響しません。フィルタは複数軸を同時に追加でき、軸間はAND・同一軸内の値はORで絞り込みます。';
$string['viz_categoryaxis'] = '科目カテゴリ';
$string['viz_majorsuffix'] = '(大項目)';
$string['viz_total'] = '合計';
$string['viz_selectall'] = '全選択';
$string['viz_selectnone'] = '全解除';
$string['viz_removefilter'] = '✕ このフィルタを削除';
$string['viz_clickforlist'] = 'セルをクリックすると該当科目の一覧を表示します。';
$string['viz_backtolist'] = '← 一覧に戻る';
$string['viz_addtocompare'] = '比較リストに追加';
$string['viz_comparelist'] = '比較リスト ({$a})';
$string['viz_comparesearchplaceholder'] = '科目名で検索して追加...';
$string['viz_remove'] = '外す';
$string['viz_opencourse'] = 'コースを開く';
$string['viz_novaluelabel'] = '未設定';
$string['viz_statline'] = '表示対象: {$a->shown} / {$a->total}科目';
$string['viz_nomatch'] = '該当する科目はありません。';
