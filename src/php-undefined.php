<?php

// Undefined variable
$myVariable;

// Accessing the undefined variable
echo $myVariable; // Output: Notice: Undefined variable: myVariable

// Using the isset() function to check if a variable is defined
if (isset($myVariable)) {
    echo "The variable is defined.";
} else {
    echo "The variable is undefined.";
} // Output: The variable is undefined.
