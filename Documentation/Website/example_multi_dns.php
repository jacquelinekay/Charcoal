<html>
<head>
<title>Multi-DNS Lookup Example</title>
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

<h1>Multi-DNS Lookup</h1>

The first example is a multi-DNS lookup procedure.  This example is
shamelessly stolen from the
paper <a href="http://static.usenix.org/event/usenix07/tech/krohn.html">Events
Can Make Sense</a> by Krohn, Kohler, and Kaashoek.

<h3>Sequential Version</h3>

<div class="highlight mono">
<table><tbody><tr>
<td align="right" valign="top">
1:<br/>2:<br/>3:<br/>4:<br/>5:<br/>6:<br/>7:<br/>8:<br/>9:<br/>10:</td>
<td>&nbsp;</td>
<td valign="top">
<i>void</i> <b>multi_dns_seq</b>(<br/>
<pre>    </pre><i>size_t</i> <b>N</b>, <i>char **</i><b>names</b>,
 <b><u>struct</u></b> <i>addrinfo **</i><b>infos</b> )<br/>
{<br/>
<pre>    </pre><i>size_t</i> <b>i</b>;<br/>
<pre>    </pre><b><u>for</u></b>( i = 0; i &lt; N; ++i )<br/>
<pre>    </pre>{<br/>
<pre>        </pre>assert( 0 == getaddrinfo(<br/>
<pre>            </pre>names[i], NULL, NULL, &infos[i] ) );<br/>
<pre>    </pre>}<br/>
}
</tr></tbody></table>
</div>

<p>In <span class="mono">multi_dns_seq</span>, we sequentially
perform <span class="mono">N</span> DNS lookups and return the results
in <span class="mono">infos</span>.  Obviously the error handling is
pretty bare-bones here; I encourage to try to look past that.</p>

<h3>Concurrent Version</h3>

<p>If we have a non-trivial number of names to look up, we
might be able to get through them faster by putting out several requests
simultaneously.  Here's how we can do that in Charcoal.</p>

<div class="highlight mono">
<table><tbody><tr>
<td align="right" valign="top">
1:<br/>2:<br/>3:<br/>4:<br/>5:<br/>6:<br/>7:<br/>8:<br/>9:<br/>10:<br/>
11:<br/>12:<br/>13:<br/>14:<br/>15:<br/>16:<br/>17:<br/>18:</td>
<td>&nbsp;</td>
<td valign="top">
<i>void</i> <b>multi_dns_conc</b>(<br/>
<pre>    </pre><i>size_t</i> <b>N</b>, <i>char</i> **<b>names</b>, <b><u>struct</u></b> <i>addrinfo</i> **<b>infos</b> )<br/>
{<br/>
<pre>    </pre><i>size_t</i> <b>i</b>, <span title="How many lookups have finished?" class="yellow"><b>done</b> = 0</span>;<br/>
<pre>    </pre><i>semaphore_t</i> <b>done_sem</b>;<br/>
<pre>    </pre>sem_init( &amp;done_sem, 0 );<br/>
<pre>    </pre><b><u>for</u></b>( i = 0; i &lt; N; ++i )<br/>
<pre>    </pre>{<br/>
<pre>        </pre><b><u><span title="The 'activate' keyword" class="yellow">activate</span></u></b> <span title="The by-value variable list" class="yellow">( i )</span><br/>
<pre>        </pre>{<br/>
<pre>            </pre>assert( 0 == getaddrinfo(<br/>
<pre>                </pre>names[i], NULL, NULL, &amp;infos[i] ) );<br/>
<pre>            </pre><b><u>if</u></b>( ( <span title="Shared variable access" class="yellow">++done</span> ) == N )<br/>
<pre>                </pre>sem_inc( &amp;done_sem );<br/>
<pre>        </pre>}<br/>
<pre>    </pre>}<br/>
<pre>    </pre><span title="Wait for the done signal" class="yellow">sem_dec( &amp;done_sem )</span>;<br/>
}
</td>
</tr></tbody></table>
</div>

<p>The most significant change here is
the <span class="mono">activate</span> expression that starts on line 9.
The expression or statement that follows
the <span class="mono">activate</span> keyword (in this example, the
block from line 10 to 15) is run concurrently with the activating code.
The activated statement runs in a new <em>activity</em>.  Activities are
cooperative in the sense that at most one activity can be executing at a
given time, and activities are never preempted to run peer
activities.</p>

<p>Activities are a kind of cooperative thread.</p>

<p>Activated statements can read and write variables declared in their
surrounding scope.  There are several instances in this example code: 
<span class="mono">N</span>, <span class="mono">i</span>,
<span class="mono">done</span>.  Some care must be taken with these
shared variables.  First, the programmer has to decide whether the new
activity should get a variable by-value or by-reference.  This is very
much like the identically named options for parameter passing in C++.
The default is by-reference.  For instance, all activities in this
example share the same <span class="mono">done</span> variable, and see
updates made by other activites.

can use any variable that a "normal" statement could use, but the
programmer has to make an important choice analogous to
by-value/by-reference parameter passing in C++.  By default variables
are treated as references.  In the example,
the <span class="mono">done</span> variable is modified by multiple
activities.  This works fine.  In contrast, each activity needs to know
what the value of <span class="mono">i</span> was at activity creation
time.  That is why we put it in the by-value variable list.  The newly
created activity gets its own copy of each of the variables in this
list.</p>

<p><a href="some_examples_mistake1.html">What if we leave out the
synchronization with <span class="mono">done_sem</span>?</a></p>

<p><a href="some_examples_mistake2.html">What if we
leave <span class="mono">i</span> off the by-value variables
list?</a></p>

<p><a href="some_examples_mistake3.html">What if we add something like
<span class="mono">done</span> to the by-value variables list?</a></p>

<h3>Concurrent Version with Simultaneous Lookup Limit</h3>

<p>One weakness of our first concurrent multi-DNS lookup is that it
tries to look up all the names simultaneously.  If the list of names is
long, this might overwhelm the operating system or network.  We can
limit the number of outstanding look ups with a few modest changes to
the code:</p>

<div class="highlight mono">
<table><tbody><tr>
<td align="right" valign="top">
1:<br/>2:<br/>3:<br/>4:<br/>5:<br/>6:<br/>7:<br/>8:<br/>9:<br/>10:<br/>
11:<br/>12:<br/>13:<br/>14:<br/>15:<br/>16:<br/>17:<br/>18:<br/>19:<br/>20:<br/>
21:<br/>22:<br/>23:<br/>24:<br/>25:</td>
<td>&nbsp;</td>
<td valign="top">
<b><u>#define</u></b> <b>DEFAULT_MULTI_DNS_LIMIT</b> 10<br/>
<br/>
<i>void</i> <b>multi_dns_conc</b>(<br/>
<pre>    </pre><i>size_t</i> <b>N</b>, <i>size_t</i> <b>lim</b>,<br/>
<pre>    </pre><i>char</i> **<b>names</b>, <b><u>struct</u></b> <i>addrinfo</i> **<b>infos</b> )<br/>
{<br/>
<pre>    </pre><i>size_t</i> <b>i</b>, <b>done</b> = 0;<br/>
<pre>    </pre><i>semaphore_t</i> <b>done_sem</b>, <b>lim_sem</b>;<br/>
<pre>    </pre>sem_init( &amp;done_sem, 0 );<br/>
<pre>    </pre>sem_init( &amp;lim_sem,<br/>
<pre>        </pre>lim &lt; 1 ? DEFAULT_MULTI_DNS_LIMIT : lim );<br/>
<pre>    </pre><b><u>for</u></b>( i = 0; i &lt; n; ++i )<br/>
<pre>    </pre>{<br/>
<pre>        </pre><span title="Wait until fewer than 'lim' lookups are happening" class="yellow">sem_dec( &amp;lim_sem )</span>;<br/>
<pre>        </pre><b><u>activate</u></b> ( i )<br/>
<pre>        </pre>{<br/>
<pre>            </pre>assert( 0 == getaddrinfo(<br/>
<pre>                </pre>names[i], NULL, NULL, &amp;infos[i] ) );<br/>
<pre>            </pre><span title="Allow waiting lookups to proceed" class="yellow">sem_inc( &amp;lim_sem )</span>;<br/>
<pre>            </pre><b><u>if</u></b>( ( ++done ) == n )<br/>
<pre>                </pre>sem_inc( &amp;done_sem );<br/>
<pre>        </pre>}<br/>
<pre>    </pre>}<br/>
<pre>    </pre>sem_dec( &amp;done_sem );<br/>
}
</td>
</tr></tbody></table>
</div>

<p>Here we added a new semaphore (<span class="mono">lim_sem</span>)
that the main activity decrements before activating each look up.  The
look up activities increment the limit semaphore after completing the
lookup, allowing subsequent activations to occur.</p>

<p>An alternative pattern that would be more appropriate if we were
using processes or threads is for the main code to create a pool of
<span class="mono">lim</span> processes/threads/activities that would
then carefully work their way through the list of names.  This worker
pool pattern is less clear for applications like this, and is only
preferable if creating new activities is expensive.  In Charcoal,
activating a statement is very cheap &mdash; much closer to the cost of
a procedure call than the cost of process creation.</p>

<div class="highlight mono">
<table><tbody><tr>
<td align="right" valign="top">
1:<br/>2:<br/>3:<br/>4:<br/>5:<br/>6:<br/>7:<br/>8:<br/>9:<br/>10:<br/>
11:<br/>12:<br/>13:<br/>14:<br/>15:<br/>16:<br/>17:<br/>18:<br/>19:<br/>20:<br/>
21:<br/>22:<br/>23:<br/>24:<br/>25:</td>
<td>&nbsp;</td>
<td valign="top">
<b><u>#define</u></b> <b>DEFAULT_MULTI_DNS_LIMIT</b> 10<br/>
<br/>
<i>void</i> <b>multi_dns_conc</b>(<br/>
<pre>    </pre><i>size_t</i> <b>N</b>, <i>size_t</i> <b>lim</b>,<br/>
<pre>    </pre><i>char</i> **<b>names</b>, <b><u>struct</u></b> <i>addrinfo</i> **<b>infos</b><br/>
<pre>    </pre><i>semaphore_t *</i><b></b>, <b><u>struct</u></b> <i>addrinfo</i> **<b>infos</b> )<br/>
{<br/>
<pre>    </pre><i>size_t</i> <b>i</b>, <b>done</b> = 0;<br/>
<pre>    </pre><i>semaphore_t</i> <b>done_sem</b>, <b>lim_sem</b>;<br/>
<pre>    </pre>sem_init( &amp;done_sem, 0 );<br/>
<pre>    </pre>sem_init( &amp;lim_sem,<br/>
<pre>        </pre>lim &lt; 1 ? DEFAULT_MULTI_DNS_LIMIT : lim );<br/>
<pre>    </pre><b><u>for</u></b>( i = 0; i &lt; n; ++i )<br/>
<pre>    </pre>{<br/>
<pre>        </pre><span title="Wait until fewer than 'lim' lookups are happening" class="yellow">sem_dec( &amp;lim_sem )</span>;<br/>
<pre>        </pre><b><u>activate</u></b> ( i )<br/>
<pre>        </pre>{<br/>
<pre>            </pre>assert( 0 == getaddrinfo(<br/>
<pre>                </pre>names[i], NULL, NULL, &amp;infos[i] ) );<br/>
<pre>            </pre><span title="Allow waiting lookups to proceed" class="yellow">sem_inc( &amp;lim_sem )</span>;<br/>
<pre>            </pre><b><u>if</u></b>( ( ++done ) == n )<br/>
<pre>                </pre>sem_inc( &amp;done_sem );<br/>
<pre>        </pre>}<br/>
<pre>    </pre>}<br/>
<pre>    </pre>sem_dec( &amp;done_sem );<br/>
}
</td>
</tr></tbody></table>
</div>

<?php include 'copyright.html'; ?>

</div>
</body>
</html>
