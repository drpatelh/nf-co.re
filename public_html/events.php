<?php

$md_base = dirname(dirname(__file__))."/markdown/";
$event_type_classes = array(
  'hackathon' => 'primary',
  'talk' => 'success',
  'tutorial' => 'info'
);

// Helper functions
function sanitise_date_meta($event){
  # Check that start date is set, delete if not
  if(!isset($event['start_date'])){
    return false;
  }
  # Check end date is set
  if(!isset($event['end_date'])) {
    $event['end_date'] = $event['start_date'];
  }
  # Parse dates
  if(!isset($event['start_time'])) $event['start_time'] = '';
  if(!isset($event['end_time'])) $event['end_time'] = '';
  $event['start_ts'] = strtotime($event['start_date'].' '.$event['start_time']);
  $event['end_ts'] = strtotime($event['end_date'].' '.$event['end_time']);
  # Check end is after start
  if($event['end_ts'] < $event['start_ts']){
    $event['end_date'] = $event['start_date'];
    $event['end_ts'] = strtotime($event['end_date'].' '.$event['end_time']);
  }
  return $event;
}


//
// SINGLE EVENT
//
if(isset($_GET['event']) && substr($_GET['event'],0,7) == 'events/'){

  // Parse the markdown before header.php, so that we can override subtitle etc
  $markdown_fn = $md_base.$_GET['event'].'.md';
  require_once('../includes/parse_md.php');

  //
  // Add event meta to the subtitle
  //

  // Event type badge
  if(isset($meta['type'])){
    $colour_class = $event_type_classes[strtolower($meta['type'])];
    $subtitle = '<span class="badge badge-'.$colour_class.'" mr-3>'.ucfirst($meta['type']).'</span> '.$subtitle;
  }

  $event = sanitise_date_meta($meta);
  if($event){
    $header_html = '<div class="row" style="margin-bottom:-1rem;"><div class="col-md-6">';
    $header_html .= '<dl>';
    // Start time
    if($event['start_time']){
      $header_html .= '<dt>Event starts:</dt><dd>'.date('H:i, j<\s\u\p>S</\s\u\p> M Y', $event['start_ts']).'</dd>';
    } else {
      $header_html .= '<dt>Event starts:</dt><dd>'.date('j<\s\u\p>S</\s\u\p> M Y', $event['start_ts']).'</dd>';
    }
    // End time
    if($event['end_ts'] > $event['start_ts'] && $event['end_time']){
      $header_html .= '<dt>Event ends:</dt><dd>'.date('H:i, j<\s\u\p>S</\s\u\p> M Y', $event['end_ts']).'</dd>';
    } else if($event['end_ts'] > $event['start_ts']){
      $header_html .= '<dt>Event ends:</dt><dd>'.date('j<\s\u\p>S</\s\u\p> M Y', $event['end_ts']).'</dd>';
    }
    $header_html .= '</dl>';
    $header_html .= '</div><div class="col-md-6">';
    // Location
    if(
        array_key_exists('location_name', $event) ||
        array_key_exists('location_url', $event) ||
        array_key_exists('address', $event) ||
        array_key_exists('location_latlng', $event)
    ) {
        $header_html .=  '<dt class="col-sm-3">Location:</dt><dd class="col-sm-9">';
        if(isset($event['location_name'])){
          if(isset($event['location_url'])){
            $header_html .=  '<a class="text-white underline" href="'.$event['location_url'].'">'.$event['location_name'].'</a>'.'<br>';
          } else {
            $header_html .=  $event['location_name'].'<br>';
          }
        } else if(isset($event['location_url'])){
          $header_html .=  '<a class="text-white underline" href="'.$event['location_url'].'">'.$event['location_url'].'</a>'.'<br>';
        }
        if(isset($event['address'])){
          $header_html .=  $event['address'].'<br>';
        }
        if(isset($event['location_latlng'])){
          $header_html .=  '<a class="mt-2 btn btn-sm btn-outline-light" href="https://www.google.com/maps/search/?api=1&query='.implode(',', $event['location_latlng']).'" target="_blank">See map</a>';
        }
        $header_html .= '</dd>';
    }
    $header_html .= '</div></div>';
  }

  $md_github_url = 'https://github.com/nf-core/nf-co.re/tree/master/markdown/'.$_GET['event'].'.md';
  include('../includes/header.php');
  include('../includes/footer.php');
  exit;
}




//
// EVENTS LISTING PAGE
//

$title = 'Events';
$subtitle = 'Details of past and future nf-core meetups.';
$md_github_url = 'https://github.com/nf-core/nf-co.re/blob/master/nf-core-events.yaml';
include('../includes/header.php');

# To get parse_md_front_matter() function
require_once('../includes/functions.php');

// Load event front-matter
$events = [];
$year_dirs = glob($md_base.'events/*', GLOB_ONLYDIR);
foreach($year_dirs as $year){
  $event_mds = glob($year.'/*.md');
  foreach($event_mds as $event_md){
    // Load the file
    $md_full = file_get_contents($event_md);
    if ($md_full !== false) {
      $fm = parse_md_front_matter($md_full);
      // Add the URL
      $fm['meta']['url'] = '/events/'.basename($year).'/'.str_replace('.md', '', basename($event_md));
      // Add to the events array
      $events[] = $fm['meta'];
    }
  }
}

# Parse dates and sort events by date
$future_events = [];
$past_events = [];
foreach($events as $idx => $event){

  $event = sanitise_date_meta($event);
  if(!$event){
    unset($events[$idx]);
    continue;
  }

  # Update arrays
  if($event['start_ts'] > time()){
    $future_events[$idx] = $event;
  } else {
    $past_events[$idx] = $event;
  }
}
# Sort future events so that the oldest is at the top
usort($future_events, function($a, $b) {
    return $a['start_ts'] - $b['start_ts'];
});
# Sort past events so that the newest is at the top
usort($past_events, function($a, $b) {
    return $b['start_ts'] - $a['start_ts'];
});

function print_events($events, $is_past_event){
  global $event_type_classes;
  foreach($events as $idx => $event):
    # Nice date strings
    $date_string = date('j<\s\u\p>S</\s\u\p> M Y', $event['start_ts']).' - '.date('j<\s\u\p>S</\s\u\p> M Y', $event['end_ts']);
    if(date('mY', $event['start_ts']) == date('mY', $event['end_ts'])){
      $date_string = date('j<\s\u\p>S</\s\u\p> ', $event['start_ts']).' - '.date('j<\s\u\p>S</\s\u\p> M Y', $event['end_ts']);
    }
    if(date('dmY', $event['start_ts']) == date('dmY', $event['end_ts'])){
      $date_string = date('j<\s\u\p>S</\s\u\p> M Y', $event['end_ts']);
    }
    $colour_class = $event_type_classes[strtolower($event['type'])];
?>

<!-- Event Card -->
<div class="card my-4 border-top-0 border-right-0 border-bottom-0 border-<?php echo $colour_class; ?>">
  <div class="card-body <?php if($is_past_event){ echo 'py-2'; } ?>">
    <h5 class="my-0 py-0">
      <small><span class="badge badge-<?php echo $colour_class; ?> float-right small"><?php echo ucfirst($event['type']); ?></span></small>
      <a class="text-success" href="<?php echo $event['url']; ?>"><?php echo $event['title']; ?></a>
    </h5>
    <?php if(array_key_exists('subtitle', $event)) {
      $tm = $is_past_event ? 'text-muted' : '';
      echo '<p class="mb-0 '.$tm.'">'.$event['subtitle'].'</p>';
    }
    if(!$is_past_event): ?>
      <h6 class="small text-muted"><?php echo $date_string; ?></h6>
      <?php if(array_key_exists('description', $event)){ echo '<p>'.nl2br($event['description']).'</p>'; } ?>
      <a href="<?php echo $event['url']; ?>" class="btn btn-outline-success">
        See details
      </a>
    <?php else: ?>
      <h6 class="small text-muted mb-0">
        <?php echo $date_string; ?> -
        <a class="text-success" href="<?php echo $event['url']; ?>">
          See details
        </a>
      </h6>
    <?php endif; ?>
  </div>
</div>

<?php
  endforeach;
}

echo '<h2 id="future_events"><a href="#future_events" class="header-link"><span class="fas fa-link" aria-hidden="true"></span></a>Upcoming Events</h2>';
if(count($future_events) > 0){
    print_events($future_events, false);
} else {
    print '<p class="text-muted">No events found</p>';
}

echo '<h2 id="past_events"><a href="#past_events" class="header-link"><span class="fas fa-link" aria-hidden="true"></span></a>Past Events</h2>';
if(count($past_events) > 0){
    print_events($past_events, true);
} else {
    print '<p class="text-muted">No events found</p>';
}

include('../includes/footer.php');
