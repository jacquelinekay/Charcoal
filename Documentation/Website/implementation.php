<html>
<head>
<title>Implementation</title>
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
&mdash; <a href="implementation.html#translation">Translation to C</a><br/>
&mdash; <a href="implementation.html#runtime">Runtime Library</a><br/>
&mdash; <a href="implementation.html#syscalls">Syscalls</a><br/>
- <a href="faq.html">FAQ</a>
</div>

<div class="main_div">

<h1>Charcoal Implementation</h1>

There are a few pieces to the Charcoal implementation:

<ul>
<li>Charcoal to C translation
<li>Charcoal runtime library
<li>System call interception
</ul>

<a id="translation"/>
<h2>5.(1/3) Charcoal to C Translation</h2>

<ul>
<li>activate extraction
<li>yield insertion
<li>unyielding translation
</ul>

<h3>5.1.(1/3) Activate</h3>

<p>The core piece of new syntax added in Charcoal is the activate
expression.  It looks like:</p>

<div class="highlight mono">
<b><u>activate</b></u> (<b>x</b>,...,<b>z</b>) <i>[&lt;expression&gt;|&lt;statement&gt;]</i>
</div>

<p>When this expression executes it starts a new activity to evaluate
its body, which can be an arbitrary statement or expression.  The whole
expression evaluates to a pointer to the new activity (which is an
opaque type).</p>

<p>The body of an activate expression is free to refer to local
variables that are in scope wherever the expression appears.  The
programmer has to choose whether the new activity gets these variables
by-reference or by-value.  The default is by-reference; in this case,
changes to the variable in either the "main" code or the activated code
will be observed by the other.  The new activity gets its own private
copy of the variables that appear in the list directly following the
activate keyword.  The initial value of these by-value variables is
whatever the corresponding variable's value was at the point when the
activity was created.</p>

<p>(In general, Charcoal programmers need to be very careful about
accessing local variables from the enclosing scope in activities.  These
variables are stack-allocated, so they'll go away as soon at the
procedure call that created the activity returns.)</p>

<p>Here are the steps used to translate activate expressions to plain
C.</p>

<h4>Expression to Statement</h4>

<p>Activating an expression is defined to be the same as activating a
statement that returns that expression.  After performing this
transformation, all activate bodies are statements.</p>

<div class="highlight mono">
<b><u>activate</b></u> (<b>x</b>,...,<b>z</b>) <i>&lt;expression&gt;</i>
<br/><hr/>
<b><u>activate</b></u> (<b>x</b>,...,<b>z</b>) <b><u>return</b></u> <i>&lt;expression&gt;</i>
</div>

<h4>"Returning" from an Activity</h4>

<p>A return statement in an activity is different from a "normal" return
statement.  Returning from an activity has 2 effects: (1) the returned
value is saved in the activity's backing memory; (2) the activity stops
executing.  The returned value can then be accessed later by other
activities.  This is especially common when using the "future" pattern.
Exactly when an activity's backing memory gets deallocated is discussed
elsewhere.</p>

<p>(This translation only happens
within <span class="mono">activate</span> bodies.)</p>

<div class="highlight mono">
<b><u>return</b></u> <i>&lt;expression&gt;</i>
<br/><hr/>
{&nbsp;self->rv = <i>&lt;expression&gt;</i>;<br/>
&nbsp;&nbsp;exit_activity; }
</div>

<h4>Variable Binding</h4>

<div class="highlight mono">
<b><u>activate</u></b> (<b>x</b>,...,<b>z</b>) <i>&lt;statement&gt;</i>
<br/><hr/>
args.a = &a; ... args.c = &c;<br/>
args.x = x; ... args.z = z;<br/>
<b><u>activate</b></u> {<br/>
&nbsp;&nbsp;x = args.x; ... z = args.z;<br/>
&nbsp;&nbsp;aptr = args.a; ... cptr = args.c;<br/>
&nbsp;&nbsp;<i>&lt;statement&gt;</i>[*aptr/a,...,*cptr/c]<br/>}
</div>

<h4>Activate Body Extraction</h4>

<p>In order to get to plain C, we extract the body of an activate
statement out into a function.</p>

<div class="highlight mono">
<b><u>activate</b></u> <i>&lt;statement&gt;</i>
<br/><hr/>
void __charcoal_activity_NNN( args )<br/>
{ <i>&lt;statement&gt;</i> }<br/>
...<br/>
&nbsp;&nbsp;__charcoal_activate( __charcoal_activity_NNN, args )</span>
</div>

<h3>5.1.(2/3) Yield Insertion</h3>

<p>The most novel part of Charcoal is that the language has implicit
yield invocations scattered about hither and yon.  This is what makes
the scheduling of Charcoal's activities a hybrid of preemptive and
cooperative.  They are cooperative in the sense that an activity must
yield in order for control to transfer to another activity (and it's
always possible to avoid/suppress yielding).  They are preemptive in the
sense that <em>by default</em> yield is implicitly invoked before
certain control flow transfers.  This gives activities a kind of "chunky"
preemptiveness that we hope will be easier for application programmers
to work with than conventional preemptive threads.</p>

<p>The intention is that most of the time you just don't have to worry
too much about either accidentally starving other activities (which can
be a problem with pure cooperative models) or invariants being violated
by another activity/thread interrupting at just the wrong time (which
can be a problem with pure preemptive models).</p>

<p>To be a little more precise, Charcoal has a yield on every
"backwards" control flow transition.  Important side note: this is
default behavior which can be overridden in variety of ways.  Every loop
(for, while) has a yield after every iteration.  gotos that go "up" in
the source have a yield.  Recursive procedure calls have a yield on both
call and return.</p>

<p>Indirect calls (e.g. through a function pointer) are a somewhat
tricky subject.  The easiest thing for the programmer is to assume that
all indirect calls have yields on call and return by default.</p>

<p>One huge difference between activities and conventional preemptive
threads is that data races
(<a href="http://blog.regehr.org/archives/490">ref 1</a>,
<a href="http://static.usenix.org/event/hotpar11/tech/final_files/Boehm.pdf">ref
2</a>) between activities simply do not exist.  Higher-level race
conditions are still possible, of course, but not having to worry about
low-level data races is a pretty big win.</p>

<h3>5.1.(3/3) Unyielding</h3>

<p>Charcoal comes with the usual set of synchronization primitives
(mutexes, condition variables, semaphores, barriers), also a nice set of
channel-based message passing primitives inspired by CML.  However,
quite often simpler and less error-prone synchronization patterns can
be used.</p>

<p>As we saw in the first example, simple shared memory accesses can
often go without any extra synchronization at all.  If you need a larger
section of code to execute atomically (relative to peer activities), you
can use the <span class="mono">unyielding</span>
keyword.  <span class="mono">unyielding</span> can be
applied to statements, expressions and procedure declarations.  In all
cases the effect is similar: the relevant statement/expression/procedure
will execute without yielding control to another activity, even if it
executes a yield statement.</p>

<div class="highlight mono">
<b><u>unyielding</u></b> <i>&lt;expression&gt;</i>
<br/><hr/>
({<pre> </pre>typeof( <i>&lt;expression&gt;</i> ) __charcoal_uny_var_NNN;<br/>
<pre>   </pre>__charcoal_unyielding_enter();<br/>
<pre>   </pre>__charcoal_uny_var_NNN = <i>&lt;expression&gt;</i>;<br/>
<pre>   </pre>__charcoal_unyielding_exit();<br/>
<pre>   </pre>__charcoal_uny_var_NNN })
</div>

<div class="highlight mono">
<b><u>unyielding</b></u> <i>&lt;statement&gt;</i>
<br/><hr/>
{&nbsp;unyielding_enter;<br/>
&nbsp;&nbsp;<i>&lt;statement&gt;</i>;<br/>
&nbsp;&nbsp;unyielding_exit&nbsp;}
</div>

<div class="highlight mono">
<b><u>unyielding</u></b> <i>&lt;fun decl&gt;</i> <i>&lt;fun body&gt;</i>
<br/><hr/>
<i>&lt;fun decl&gt;</i><br/>
{&nbsp;unyielding_enter;<br/>
&nbsp;&nbsp;<i>&lt;fun body&gt;</i>;<br/>
&nbsp;&nbsp;unyielding_exit&nbsp;}</span>
</div>

In all three cases, the "body" of
the <span class="mono">unyielding</span> annotation
must be scanned for control transfers that might escape the body
(<span class="mono">return</span>,
<span class="mono">break</span>,
<span class="mono">goto</span>, ...).
An <span class="mono">unyielding_exit</span> is
inserted directly before such transfers.

<h4>When Should Unyielding Be Used?</h4>

<p>Unyielding can be used to make arbitrary chunks of code
"activity-safe", but some care needs to be taken in its use.  A good
example of its use is in a binary tree library.  Procedures that modify
a tree can/should be marked unyielding.</p>

<p>Probably most simple leaf procedures should be marked unyielding.<p>

<p>You need to be a little careful with
the <span class="mono">unyielding</span> annotation.
It's easy to starve other activities by entering an unyielding
statement, then executing a long-running loop or making a blocking
syscall.  The situation to be especially vigilant about is using
unyielding on a block that usually executes reasonably quickly, but has
a path that is both infrequently executed and long-running/blocking.</p>

<p>Marking an expression/statement/procedure unyielding is very cheap.
In the worst case you add an increment and decrement of a thread-local
variable.  If the compiler can see nested unyielding statements, it can
optimize them out pretty easily, too.</p>

<h3>5.1.(4/3) Synchronized</h3>

<div class="highlight mono">
<b><u>synchronized</u></b> (<i>&lt;expression&gt;</i>) <i>&lt;statement&gt;</i>
<br/><hr/>
({<br/>
<pre>    </pre>mutex_t *__charcoal_sync_mutex_ ## __COUNTER__ = <i>&lt;expression&gt;</i>;<br/>
<pre>    </pre>mutex_acquire( __charcoal_sync_mutex_NNN ) ? 1 :<br/>
<pre>        </pre>({ <i>&lt;statement&gt;</i>;<br/>
<pre>           </pre>mutex_release( __charcoal_sync_mutex_NNN ) ? 2 : 0 })<br/>
})
</div>

<a id="runtime"/>
<h2>5.(2/3) Charcoal Runtime Library</h2>

<ul>
<li>yield
<li>scheduler
<li>stacks
</ul>

<h3>5.2.(1/3) yield</h3>

Well-behaved Charcoal programs invoke yield quite often (just for a
rough sense of scale, maybe a few times per microsecond on modern
processors).  This high frequency means that yield must be engineered
to:

<ul>
<li>Execute extremely quickly in the common case.
<li>Not cause a context switch most of the time.
</ul>

The first goal is important for based cycle counting reasons.  If yield
takes more than 10 or 20 instructions in the common case, avoiding
yields will become more of a performance tuning thing in Charcoal than I
want it to be.  Need to do some testing here.

The second goal is important because context switching has a non-trivial
direct cost (smaller than threads, but still not zero).  More
importantly, context switching can have high indirect costs if different
activities evict each others' working data from the caches.

<h3>5.2.(2/3) Scheduler</h3>

Under programmer control?

Interesting things about determinism?

<h3>5.2.(3/3) Stacks</h3>

One of the reasons threads are relatively expensive in most
implementations is that a large amount of memory has to be pre-allocated
for each thread's stack.  I really wanted to avoid that, so for now
we're using gcc's <a href="http://gcc.gnu.org/wiki/SplitStacks">split
stacks</a> and/or
LLVM's <a href="http://lists.cs.uiuc.edu/pipermail/llvmdev/2011-April/039260.html">segmented
stacks</a>.  One could go really extreme and heap allocate every
procedure call record individually.  I'm not aware of an easy way to do
that today, so that's future work.

<a id="syscalls"/>
<h2>5.(3/3) System Call Interception</h2>

Charcoal programs cannot be allowed to directly make syscalls that might
block for a long time, because multiple activities reside in a single
thread and a blocking syscall would block the whole thread.  The current
Charcoal implementation
uses <a href="http://software.intel.com/en-us/articles/pin-a-dynamic-binary-instrumentation-tool">Pin</a>
to intercept and translate syscalls.  (Other dynamic binary translation
tools like <a href="http://valgrind.org/">Valgrind</a> would probably
work just as well.)  The Charcoal runtime keeps a small pool of idle
threads meant for running syscalls.  When Pin intercepts a syscall, it
"moves" the call over to one of the idle threads and lets the scheduler
switch over to another activity on the calling thread.

<?php include 'copyright.html'; ?>

</div>
</body>
</html>
