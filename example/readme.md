rEst of tables (departmens collection):

[
{name:"departments", type: "t2coll",        
    table: "departments",                     
    collname:"departments",                  
    path:"[dept_no]"                   
    },  
{name:"departments_employees", type: "t2docs", 
    table: "dept_emp",                     
    parent: "departments",                
    path:"[dept_no].employees.[emp_no]",  
    },                                    
{name:"departments_managers", type: "t2docs", 
    table: "dept_manager",                       
    parent: "departments",                   
    path:"[dept_no].managers.[emp_no]",    
    }                                      
]

