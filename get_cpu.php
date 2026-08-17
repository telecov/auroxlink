<?php
    $load = sys_getloadavg();
    $cores = (int) shell_exec("nproc");
    echo round(($load[0] / $cores) * 100, 2);
