<?php defined('SYSPATH') OR die('No direct access allowed.'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <meta name="keywords" content="Waterloo University API data uw uwdata course calendar" />
  <meta name="description" content="UWData.ca is the premiere source for building applications upon the UW data set. It's also Waterloo's first public API." />
  <title><?php echo html::specialchars($title) ?></title>
<?
  echo html::stylesheet(array('css/reset', 'css/common'), null, FALSE);
  echo html::stylesheet($css_files, null, FALSE);
?>
</head>
<body>
<div id="page-wrapper">
<div id="page">
<div id="pageheader">
  <div class="fixedwidth">
    <div class="title"><a href="/"><span class="uwcolor">uw</span>data<span class="subtitle">.ca</span></a></div>
    <ul>
      <li><a href="/contribute">Contribute</a></li>
      <li><a href="/learnmore">Learn more</a></li>
      <li><a href="/signup" class="standout">Request an API key</a></li>
    </ul>
  </div>
</div>
<div id="pageheadershadow"></div>

<div class="fixedwidth">
<div id="content-frame-top"></div>
<div id="content-frame">
<div id="content">
  <?php echo $content ?>

</div><!-- content -->
</div><!-- content-frame -->
<div class="clearfix"></div>
<div id="content-frame-bottom"></div>

<div class="clearfix"></div>

</div> <!-- fixedwidth -->

<div class="clearfix"></div>
<div id="page-footer"></div>
</div> <!-- page -->

</div> <!-- pagewrapper -->

<div id="footer">
  <div class="fixedwidth">
    <div class="copyright">
      © Copyright 2010<br/>All content licensed under the <a href="http://www.apache.org/licenses/LICENSE-2.0">Apache License</a>, unless otherwise noted.<br/><br/>
      All data is gathered from the University of Waterloo's public information sets.<br/><br/>
      Yet another project by <a href="http://JeffVerkoeyen.com/">Jeff Verkoeyen</a>.
    </div>
    <div class="attribution">
<? if (!IN_PRODUCTION) { ?>
      Rendered in {execution_time} seconds, using {memory_usage} of memory.<br/>
<? } ?>
      Source hosted on <a href="http://github.com/jverkoey/uwdata.ca">github</a>.<br/>
      Made with the <a href="http://kohanaphp.com/">Kohana</a> framework.<br/><br/>
      <a href='http://www.pledgie.com/campaigns/8091'><img alt='Click here to lend your support to: uwdata.ca server costs and make a donation at www.pledgie.com !' src='http://www.pledgie.com/campaigns/8091.png?skin_name=chrome' border='0' /></a>
    </div>
  </div>
</div>

<? echo html::script($js_foot_files, FALSE); ?>

<? if (IN_PRODUCTION) { ?>
<script type="text/javascript">
var uservoiceOptions = {
  /* required */
  key: 'uwdata',
  host: 'uwdata.uservoice.com', 
  forum: '38662',
  showTab: true,  
  /* optional */
  alignment: 'left',
  background_color:'#333', 
  text_color: 'white',
  hover_color: '#999',
  lang: 'en'
};

function _loadUserVoice() {
  var s = document.createElement('script');
  s.setAttribute('type', 'text/javascript');
  s.setAttribute('src', ("https:" == document.location.protocol ? "https://" : "http://") + "cdn.uservoice.com/javascripts/widgets/tab.js");
  document.getElementsByTagName('head')[0].appendChild(s);
}
_loadSuper = window.onload;
window.onload = (typeof window.onload != 'function') ? _loadUserVoice : function() { _loadSuper(); _loadUserVoice(); };
</script>

<script type="text/javascript">
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
try {
var pageTracker = _gat._getTracker("UA-12528590-1");
pageTracker._setDomainName(".uwdata.ca");
pageTracker._trackPageview();
} catch(err) {}</script>
<? } ?>

</body>
</html>