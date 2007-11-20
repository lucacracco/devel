<?php
// $Id$
// If not in 'safe mode', increase the maximum execution time:
if (!ini_get('safe_mode')) {
  set_time_limit(240);
}

/**
 * Generate some random users.
 *
 * @param $num
 *  Number of users to generate.
 * @param $kill
 *  Boolean that indicates if existing users should be removed first.
 */
function devel_create_users($num, $kill) {
  $url = parse_url($GLOBALS['base_url']);
  if ($kill) {
    db_query('DELETE FROM {users} WHERE uid > 1');
    drupal_set_message(t('Users deleted.'));
  }
  for ($i = 0; $i < $num; $i++) {
    $length = rand(6, 12);
    $name = devel_generate_word($length);
    $pass = md5(user_password());
    $mail = $name .'@'. $url['host'];
    $now = time();
    db_query("INSERT INTO {users} (name, pass, mail, status, created, access) VALUES ('%s', '%s', '%s', %d, %d, %d)", $name, $pass, $mail, 1, $now, $now);
  }
  drupal_set_message(t('!num_users created.', array('!num_users' => format_plural($num, '1 user', '@count users'))));
}

function devel_generate_content($num_nodes, $num_comments, $title_length, $kill, $add_upload = FALSE, $add_terms = FALSE, $add_alias = FALSE, $node_types) {
  if ($kill) {
    $sql = 'SELECT nid FROM {node} WHERE type IN ('. db_placeholders($node_types, 'text'). ')';
    $result = db_query($sql, $node_types);
    while ($row = db_fetch_object($result)) {
      node_delete($row->nid);
    }
  }

  // Get user id.
  $users = devel_get_users();

  // Create $num_nodes pseudo-random nodes and comments (if specified)
  devel_create_nodes($num_nodes, $users, $title_length, $add_upload, $add_terms, $num_comments, $add_alias, $node_types);
  drupal_set_message(format_plural($num_nodes, '1 node created.', '@count nodes created'));
}

function devel_create_nodes($records, $users, $title_length = 8, $add_upload = FALSE, $add_terms = FALSE, $num_comments = 0, $add_alias = FALSE, $node_types = array()) {
  
  $users = array_merge($users, array('0'));
  // $nids = array();

  if (count($node_types)) {
    // Insert new data:
    for ($i = 1; $i <= $records; $i++) {
      $node->type = $node_types[array_rand($node_types)];
      require_once './modules/node/node.pages.inc';
      node_object_prepare($node);
      $node->uid = $users[array_rand($users)];

      $node->title = devel_create_greeking(rand(1, $title_length), TRUE);
      $node->body = "node ($node->type) - ". devel_create_content();
      $node->teaser = node_teaser($node->body);
      $node->filter = variable_get('filter_default_format', 1);
      $node->format = FILTER_FORMAT_DEFAULT;
      $node->language = '';
      $node->revision = rand(0,1);
      $node->promote = rand(0, 1);
      $node->created = time();
      
      // Add an url alias
      // TODO: the nid is not known yet. move back to post creation.
      // if ($add_alias) {
      //   $node->path = "node-$node->nid-$node->type";
      // }
      
      if ($add_upload) {
        devel_generate_add_upload($node);
      }
      
      if ($add_terms) {
        devel_generate_add_terms($node);
      }
      
      // Save the node:
      node_save($node);
      
      if ($num_comments) {
        devel_generate_add_comments($node, $users, $num_comments, $title_length);
      }

      unset($node);
    }
  }
}

function devel_generate_add_comments($node, $users, $num_comments, $title_length = 8) {
  // Insert new data:
  for ($i = 1; $i <= $num_comments; $i++) {
    $comment->nid = $node->nid;
    $comment->cid = NULL;
    $comment->format = FILTER_FORMAT_DEFAULT;
    $comment->name = 'devel generate';
    $comment->mail = 'devel_generate@example.com';

    switch ($i % 3) {
      case 1:
        $comment->pid = db_result(db_query_range("SELECT cid FROM {comments} WHERE pid = 0 AND nid = %d ORDER BY RAND()", $comment->nid, 0, 1));
        break;
      case 2:
        $comment->pid = db_result(db_query("SELECT cid FROM {comments} WHERE pid > 0 AND nid = %d ORDER BY RAND()", $comment->nid, 0, 1));
        break;
      default:
        $comment->pid = 0;
    }

    $comment->subject = devel_create_greeking(rand(1, $title_length), TRUE);
    $comment->comment = devel_create_content();
    $comment->uid = $users[array_rand($users)];

    // this is slow but gets the threading right.
    comment_save((array)$comment);

  }
}

function devel_generate_vocabs($records, $maxlength = 12, $types = array('story', 'blog', 'forum', 'page')) {
  $vocs = array();

  // Insert new data:
  for ($i = 1; $i <= $records; $i++) {
    $voc = array();
    $voc['name'] = devel_generate_word(rand(2, $maxlength));
    $voc['description'] = "description of ". $voc['name'];
    $voc['nodes'] = array_flip(array($types[array_rand($types)]));
    foreach ($voc['nodes'] as $key => $value) {
      $voc['nodes'][$key] = $key;
    }
    $voc['multiple'] = 1;
    $voc['required'] = 0;
    $voc['relations'] = 1;
    $voc['hierarchy'] = 1;
    $voc['weight'] = rand(0,10);

    taxonomy_save_vocabulary($voc);
    $vocs[] = $voc['name'];
  }
  return $vocs;
}

function devel_generate_terms($records, $vocs, $maxlength = 12) {
  $terms = array();

  // Insert new data:
  for ($i = 1; $i <= $records; $i++) {
    switch ($i % 2) {
      case 1:
        $term['vid'] = $vocs[array_rand($vocs)];
        // dont set a parent. handled by taxonomy_save_term()
        // $term->parent = 0;
        break;
      case 2:
      default:
        $parent = db_fetch_object(db_query_range("SELECT t.tid, v.vid FROM {term_data} t INNER JOIN {vocabulary} v ON t.vid = v.vid ORDER BY RAND()", 0, 1));
        $term['parent'] = array($parent->tid);
        $term['vid'] = $parent->vid;
        break;
    }

    $term['name'] = devel_generate_word(rand(2, $maxlength));
    $term['description'] = "description of ". $term['name'];
    $term['weight'] = rand(0,10);

    $status = taxonomy_save_term($term);
    $output = NULL;

    if ($status) {
      $terms[] = $term['name'];
    }

    unset($term);
  }
  return $terms;
}

function devel_generate_get_vocabs() {
  $vocs = array();
  $result = db_query("SELECT vid FROM {vocabulary}");
  while($voc = db_fetch_object($result)){
    $vocs[] = $voc->vid;
  }
  return $vocs;
}

function devel_generate_taxonomy_data($num_vocab, $num_terms, $title_length, $kill) {

  if ($kill) {
    db_query("DELETE FROM {term_data}");
    db_query("DELETE FROM {term_node}");
    db_query("DELETE FROM {term_hierarchy}");
    db_query("DELETE FROM {term_relation}");
    db_query("DELETE FROM {term_synonym}");
    db_query("DELETE FROM {vocabulary}");
    db_query("DELETE FROM {vocabulary_node_types}");
    switch ($GLOBALS['db_type']) {
      case 'mysql':
      case 'mysqli':
        db_query("ALTER TABLE {vocabulary} AUTO_INCREMENT = 1");
        db_query("ALTER TABLE {term_data} AUTO_INCREMENT = 1");
        break;
      case 'pgsql':
        db_query("SELECT setval('{vocabulary}_vid_seq', 1, false)");
        db_query("SELECT setval('{term_data}_tid_seq', 1, false)");
        break;
    }
    drupal_set_message(t('Deleted taxonomy.'));
  }

  $new_vocs = devel_generate_vocabs($num_vocab, $title_length);
  if (!empty($new_vocs)) {
    drupal_set_message(t('Created the following new vocabularies: !vocs', array('!vocs' => theme('item_list', $new_vocs))));
  }
  $vocs = devel_generate_get_vocabs();
  $new_terms = devel_generate_terms($num_terms, $vocs, $title_length);
  if (!empty($new_terms)) {
    drupal_set_message(t('Created the following new terms: !terms', array('!terms' => theme('item_list', $new_terms))));
  }
}

function devel_generate_word($length){
  srand((double)microtime()*1000000);

  $vowels = array("a", "e", "i", "o", "u");
  $cons = array("b", "c", "d", "g", "h", "j", "k", "l", "m", "n", "p", "r", "s", "t", "u", "v", "w", "tr",
  "cr", "br", "fr", "th", "dr", "ch", "ph", "wr", "st", "sp", "sw", "pr", "sl", "cl", "sh");

  $num_vowels = count($vowels);
  $num_cons = count($cons);
  $word = '';

  while(strlen($word) < $length){
    $word .= $cons[rand(0, $num_cons - 1)] . $vowels[rand(0, $num_vowels - 1)];
  }

  return substr($word, 0, $length);
}

function devel_create_content() {
  $nparas = rand(1,12);
  $type = rand(0,3);

  $output = "";
  switch($type % 3) {
    case 1: // html
      for ($i = 1; $i <= $nparas; $i++) {
        $output .= devel_create_para(rand(10,60),1);
      }
      break;

    case 2: // brs only
      for ($i = 1; $i <= $nparas; $i++) {
        $output .= devel_create_para(rand(10,60),2);
      }
      break;

    default: // plain text
      for ($i = 1; $i <= $nparas; $i++) {
        $output .= devel_create_para(rand(10,60)) ."\n\n";
      }
  }

  return $output;
}

function devel_create_para($words, $type = 0) {
  $output = "";
  switch ($type) {
    case 1:
      $output .= "<p>";
      $output .= devel_create_greeking($words);
      $output = trim($output) ."</p>";
      break;

    case 2:
      $output .= devel_create_greeking($words);
      $output = trim($output) ."<br />";
      break;

    default:
      $output .= devel_create_greeking($words);
      $output = trim($output);
  }
  return $output;
}

function devel_create_greeking($words, $title = FALSE) {
  $dictionary = array("abbas", "abdo", "abico", "abigo", "abluo", "accumsan",
    "acsi", "ad", "adipiscing", "aliquam", "aliquip", "amet", "antehabeo",
    "appellatio", "aptent", "at", "augue", "autem", "bene", "blandit",
    "brevitas", "caecus", "camur", "capto", "causa", "cogo", "comis",
    "commodo", "commoveo", "consectetuer", "consequat", "conventio", "cui",
    "damnum", "decet", "defui", "diam", "dignissim", "distineo", "dolor",
    "dolore", "dolus", "duis", "ea", "eligo", "elit", "enim", "erat",
    "eros", "esca", "esse", "et", "eu", "euismod", "eum", "ex", "exerci",
    "exputo", "facilisi", "facilisis", "fere", "feugiat", "gemino",
    "genitus", "gilvus", "gravis", "haero", "hendrerit", "hos", "huic",
    "humo", "iaceo", "ibidem", "ideo", "ille", "illum", "immitto",
    "importunus", "imputo", "in", "incassum", "inhibeo", "interdico",
    "iriure", "iusto", "iustum", "jugis", "jumentum", "jus", "laoreet",
    "lenis", "letalis", "lobortis", "loquor", "lucidus", "luctus", "ludus",
    "luptatum", "macto", "magna", "mauris", "melior", "metuo", "meus",
    "minim", "modo", "molior", "mos", "natu", "neo", "neque", "nibh",
    "nimis", "nisl", "nobis", "nostrud", "nulla", "nunc", "nutus", "obruo",
    "occuro", "odio", "olim", "oppeto", "os", "pagus", "pala", "paratus",
    "patria", "paulatim", "pecus", "persto", "pertineo", "plaga", "pneum",
    "populus", "praemitto", "praesent", "premo", "probo", "proprius",
    "quadrum", "quae", "qui", "quia", "quibus", "quidem", "quidne", "quis",
    "ratis", "refero", "refoveo", "roto", "rusticus", "saepius",
    "sagaciter", "saluto", "scisco", "secundum", "sed", "si", "similis",
    "singularis", "sino", "sit", "sudo", "suscipere", "suscipit", "tamen",
    "tation", "te", "tego", "tincidunt", "torqueo", "tum", "turpis",
    "typicus", "ulciscor", "ullamcorper", "usitas", "ut", "utinam",
    "utrum", "uxor", "valde", "valetudo", "validus", "vel", "velit",
    "veniam", "venio", "vereor", "vero", "verto", "vicis", "vindico",
    "virtus", "voco", "volutpat", "vulpes", "vulputate", "wisi", "ymo",
    "zelus");

  $greeking = "";

  if (!$title) {
	  while ($words > 0) {
	    $sentence_length = rand(3,10);

	    $greeking .= ucfirst($dictionary[array_rand($dictionary)]);
	    for ($i = 1; $i < $sentence_length; $i++) {
	      $greeking .= " " . $dictionary[array_rand($dictionary)];
	    }

	    $greeking .= ". ";
	    $words -= $sentence_length;
	  }
  }
  else {
  	// use different method for titles
  	$title_length = $words;
  	$array = array();
  	for ($i = 0; $i < $words; $i++) {
  		$array[] = $dictionary[array_rand($dictionary)];
  	}
  	$greeking = ucwords(implode(' ', $array));
  }
  return $greeking;
}

function devel_generate_add_terms(&$node) {
  $vocabs = taxonomy_get_vocabularies($node->type);
  foreach ($vocabs as $vocab) {
    $sql = "SELECT tid FROM term_data WHERE vid = %d ORDER BY RAND()";
    $result = db_query_range($sql, $vocab->vid, 0, 5);
    while ($row = db_fetch_object($result)) {
      if ($vocab->tags) {
        $node->taxonomy['tags'][] = $row->tid;
      }
      else {
        $node->taxonomy[$row->tid] = $row->tid;
      }
      if (!$vocab->multiple) {
        break;
      }
    }
  }
}

function devel_get_users() {
  $users = array();
  $result = db_query_range("SELECT uid FROM {users}", 0, 50);
  while($user = db_fetch_object($result)){
    $users[] = $user->uid;
  }
  return $users;
}

function devel_generate_add_upload(&$node) {
  $source = 'misc/blog.png';
  $size = filesize($source);
  
  // $after this call, $source contains the new path.
  file_copy($source);
  $file = new stdClass();
  $file->filename = 'blog.png';
  $file->filepath = $source;
  $file->filemime = 'image/png';
  $file->list = variable_get('upload_list_default', TRUE);
  $file->description = 'b log.png was here';
  $file->filesize = $size;

  // If we made it this far it's safe to record this file in the database.
  db_query("INSERT INTO {files} (uid, filename, filepath, filemime, filesize, status, timestamp) VALUES (%d, '%s', '%s', '%s', %d, %d, %d)", $node->uid, $file->filename, $file->filepath, $file->filemime, $file->filesize, FILE_STATUS_TEMPORARY, time());
  $file->fid = db_last_insert_id('files', 'fid');
  
  $_SESSION['upload_files'][$file->fid] = $file;
  $node->files[$file->fid] = $file;
}