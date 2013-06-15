<html>
<head>
<title>Charcoal Example: Lazy Initialization</title>
<link rel="stylesheet" media="screen" type="text/css" href="charcoal.css"/>
</head>
<body style="background-color:darkgray">

<div class="side_links">
<a href="index.html">Charcoal</a><br/>
- <a href="short_version.html">Why Charcoal?</a><br/>
- <a href="some_examples.html">Examples</a><br/>
&mdash; <a href="example_multi_dns.html">Multi-DNS</a><br/>
&mdash; <a href="example_signal_handling.html">Signals</a><br/>
&mdash; <a href="example_tight_loops.html">Loops</a><br/>
&mdash; <a href="example_data_structure.html">Data structures</a><br/>
&mdash; <a href="example_lazy_initialization.html">Singleton</a><br/>
&mdash; <a href="example_asynch_exceptions.html">Asynchronous</a><br/>
- <a href="concurrency.html">Concurrency</a><br/>
- <a href="big_four.html">vs. Threads, etc.</a><br/>
- <a href="implementation.html">Implementation</a><br/>
- <a href="faq.html">FAQ</a>
</div>

<div class="main_div">

<h1>Singleton/Lazy Initialization</h1>

<p>A common programming pattern is the <en>singleton object</em>, which
is often initialized lazily.  The idea is that there is some unique
object that we don't want to initialize until (unless) it is needed.
Here is an example:</p>

<div class="highlight mono">
<table><tbody><tr>
<td align="right" valign="top">
1:<br/>2:<br/>3:<br/>4:<br/>5:<br/>6:<br/>7:<br/>8:<br/>9:<br/>10:<br/>
11:</td>
<td>&nbsp;</td>
<td valign="top">
<i>special_object_t *</i><b>get_special_object</b>( <i>void</i> )<br/>
{<br/>
<pre>    </pre><b><u>static</u></b> <i>special_object_t</i> <b>o</b>;<br/>
<pre>    </pre><b><u>static</u></b> <i>int</i> <b>initialized</b> = 0;<br/>
<pre>    </pre><b><u>if</u></b>( !initialized )<br/>
<pre>    </pre>{<br/>
<pre>        </pre>init_special_object( &amp;o );<br/>
<pre>        </pre>initialized = 1;<br/>
<pre>    </pre>}<br/>
<pre>    </pre><b><u>return</u></b> &amp;o;<br/>
}
</tr></tbody></table>
</div>

<p>This code works fine in a sequential context,
but <a href="http://www.cs.umd.edu/~pugh/java/memoryModel/DoubleCheckedLocking.html">fails
badly in a multithreaded context</a>.  In the (very) special case that
the call
to <span class="mono">init_special_object</span> is
guaranteed to not yield, this code is actually activity-safe, because
there is nothing else to trigger a yield in this code, so the whole
procedure will execute atomically.  If there is some chance
that <span class="mono">init_special_object</span> will
yield, we need something more to prevent the situation where multiple
activities enter the conditional block and initialize the special object
more than once.</p>

<p>As long
as <span class="mono">init_special_object</span> is
guaranteed to run in a modest amount of time, you can implement lazy
initialization in Charcoal like so:</p>

<div class="highlight mono">
<table><tbody><tr>
<td align="right" valign="top">
1:<br/>2:<br/>3:<br/>4:<br/>5:<br/>6:<br/>7:<br/>8:<br/>9:<br/>10:<br/>
11:<br/>12:<br/>13:<br/>14:</td>
<td>&nbsp;</td>
<td valign="top">
<i>special_object_t *</i><b>get_special_object</b>( <i>void</i> )<br/>
{<br/>
<pre>    </pre><b><u>static</u></b> <i>special_object_t</i> <b>o</b>;<br/>
<pre>    </pre><b><u>static</u></b> <i>int</i> <b>initialized</b> = 0;<br/>
<pre>    </pre><span class="yellow"><b><u>unyielding</u></b></span><br/>
<pre>    </pre>{<br/>
<pre>        </pre><b><u>if</u></b>( !initialized )<br/>
<pre>        </pre>{<br/>
<pre>            </pre>init_special_object( &amp;o );<br/>
<pre>            </pre>initialized = 1;<br/>
<pre>        </pre>}<br/>
<pre>    </pre>}<br/>
<pre>    </pre><b><u>return</u></b> &amp;o;<br/>
}
</tr></tbody></table>
</div>

<p>If <span class="mono">init_special_object</span>
might run for a long time, or block indefinitely, this is not a good
solution.  Our activities will be starved.  We want to block only
activites that need the special object, instead of all activities.  We
can use the standard implementation that works for multithreaded code,
too.</p>

<div class="highlight mono">
<table><tbody><tr>
<td align="right" valign="top">
1:<br/>2:<br/>3:<br/>4:<br/>5:<br/>6:<br/>7:<br/>8:<br/>9:<br/>10:<br/>
11:<br/>12:<br/>13:<br/>14:<br/>15:</td>
<td>&nbsp;</td>
<td valign="top">
<i>special_object_t *</i><b>get_special_object</b>( <i>void</i> )<br/>
{<br/>
<pre>    </pre><b><u>static</u></b> <i>special_object_t</i> <b>o</b>;<br/>
<pre>    </pre><span class="yellow"><b><u>static</u></b> <i>mutex_t</i> <b>mtx</b> = STATIC_MUTEX_INIT;</span><br/>
<pre>    </pre><b><u>static</u></b> <i>int</i> <b>initialized</b> = 0;<br/>
<pre>    </pre><span class="yellow"><b><u>synchronized</u></b>( &amp;mtx )</span><br/>
<pre>    </pre>{<br/>
<pre>        </pre><b><u>if</u></b>( !initialized )<br/>
<pre>        </pre>{<br/>
<pre>            </pre>init_special_object( &amp;o );<br/>
<pre>            </pre>initialized = 1;<br/>
<pre>        </pre>}<br/>
<pre>    </pre>}<br/>
<pre>    </pre><b><u>return</u></b> &amp;o;<br/>
}
</tr></tbody></table>
</div>

<p>Why is this an interesting example if we end up with more or less
exactly the same code you'd write in a multithreaded context anyway?
The reason is that synchronization operations between activites
are <em>extremely cheap</em>.  The whole reason that the double-checked
locking issue has been discussed and written about so much is that some
programmers think it's important to avoid synchronization operations
whenever possible.  It's debatable how much of a performance issue this
really is in languages like Java.  But in Charcoal there is not even a
debate.  Synchronization operations between activities so cheap that
there is no reason to avoid them for performance considerations.</p>

<p>If you happen to be clinically insane and won't tolerate even a small
handful of instructions for acquiring and releasing the mutex, you can
actually implement some of the dodgier versions of double-checked
locking in Charcoal.</p>

<div class="highlight mono">
<table><tbody><tr>
<td align="right" valign="top">
1:<br/>2:<br/>3:<br/>4:<br/>5:<br/>6:<br/>7:<br/>8:<br/>9:<br/>10:<br/>
11:<br/>12:<br/>13:<br/>14:<br/>15:<br/>16:<br/>17:</td>
<td>&nbsp;</td>
<td valign="top">
<i>special_object_t *</i><b>get_special_object</b>( <i>void</i> )<br/>
{<br/>
<pre>    </pre><b><u>static</u></b> <i>special_object_t</i> <b>o</b>;<br/>
<pre>    </pre><b><u>static</u></b> <i>mutex_t</i> <b>mtx</b> = STATIC_MUTEX_INIT;<br/>
<pre>    </pre><b><u>static</u></b> <i>int</i> <b>initialized</b> = 0;<br/>
<pre>    </pre><span class="yellow"><b><u>if</u></b>( initialized )</span><br/>
<pre>        </pre><span class="yellow"><b><u>return</u></b> &amp;o;</span><br/>
<pre>    </pre><b><u>synchronized</u></b>( &amp;mtx )<br/>
<pre>    </pre>{<br/>
<pre>        </pre><b><u>if</u></b>( !initialized )<br/>
<pre>        </pre>{<br/>
<pre>            </pre>init_special_object( &amp;o );<br/>
<pre>            </pre>initialized = 1;<br/>
<pre>        </pre>}<br/>
<pre>    </pre>}<br/>
<pre>    </pre><b><u>return</u></b> &amp;o;<br/>
}
</tr></tbody></table>
</div>

<p>This version works, but is more complex than the previous one, and I
cannot imagine that the performance difference is perceptible in any
real application.</p>

<?php include 'copyright.html'; ?>

</div>
</body>
</html>