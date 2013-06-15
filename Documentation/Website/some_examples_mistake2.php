<html>
<head>
<title>Some Examples</title>
<link rel="stylesheet" media="screen" type="text/css" href="charcoal.css"/>
</head>
<body style="background-color:darkgray">

<div class="side_links">
<a href="index.html">Charcoal</a><br/>
- <a href="short_version.html">Why Charcoal?</a><br/>
- <a href="some_examples.html">Examples</a><br/>
- <a href="concurrency.html">Concurrency</a><br/>
- <a href="big_four.html">vs. Threads, etc.</a><br/>
- <a href="implementation.html">Implementation</a><br/>
- <a href="faq.html">FAQ</a>
</div>

<div class="main_div">

<p>What if we leave <span class="mono">i</span> off the by-value
variables list?</p>

<div class="highlight" class="mono">
<table><tbody><tr>
<td align="right" valign="top">
1:<br/>2:<br/>3:<br/>4:<br/>5:<br/>6:<br/>7:<br/>8:<br/>9:<br/>10:<br/>
11:<br/>12:<br/>13:<br/>14:<br/>15:<br/>16:<br/>17:<br/>18:</td>
<td>&nbsp;</td>
<td valign="top">
<i>void</i> <b>multi_dns_conc</b>(<br/>
<pre>    </pre><i>size_t</i> <b>N</b>, <i>char</i> **<b>names</b>, <b><u>struct</u></b> <i>addrinfo</i> **<b>infos</b> )<br/>
{<br/>
<pre>    </pre><i>size_t</i> <b>i</b>, <b>done</b> = 0;<br/>
<pre>    </pre><i>semaphore_t</i> <b>done_sem</b>;<br/>
<pre>    </pre>sem_init( &amp;done_sem, 0 );<br/>
<pre>    </pre><b><u>for</u></b>( i = 0; i &lt; N; ++i )<br/>
<pre>    </pre>{<br/>
<pre>        </pre><b><u>activate</u></b> <span class="yellow">&nbsp;&nbsp;&nbsp;&nbsp;</span><br/>
<pre>        </pre>{<br/>
<pre>            </pre>assert( 0 == getaddrinfo(<br/>
<pre>                </pre>names[i], NULL, NULL, &amp;infos[i] ) );<br/>
<pre>            </pre><b><u>if</u></b>( ( ++done ) == N )<br/>
<pre>                </pre>sem_inc( &amp;done_sem );<br/>
<pre>        </pre>}<br/>
<pre>    </pre>}<br/>
<pre>    </pre>sem_dec( &amp;done_sem );<br/>
}
</td>
</tr></tbody></table>
</div>

<p>This is a pretty nasty little bug, because the code will probably
work most of the time.  The potential problem is the following
sequence:</p>

<ol>
<li>One of the lookup activities yields
<li>The "main" activity increments <span class="mono">i</span>
<li>The lookup activity resumes and now has the wrong value
for <span class="mono">i</span>
</ol>

<p>This kind of bug deserves some more thinking.  Maybe some automatic
warnings could happen.</p>

</div>
</body>
</html>