mysql2mongo
===========

MySQL to MongoDB importer

Work based on http://code.google.com/p/php-sql-parser/


The scripts are using simple relational to non-relational (r2n) mapping mechanism that should let You automagically convert Your MySQL database into MongoDB set of collections.

Example (Wordpress users):
===========

The tables(source of data):
---

	wp_users (
	  	`ID` bigint(20) 
	  	`user_login`
	 	`user_pass`
		....
		);


	wp_usermeta (
	  	`umeta_id` bigint(20)
	  	`user_id` bigint(20)        <---- 1/n relation to users
	  	`meta_key` varchar(255) 
	  	`meta_value` longtext,
		....
		);


The mapping(conversion definition):
---


{[
{name:"users", type: "t2coll", 			// mapping named "users" type "t2coll" (table as collection)
	table: "wp_users", 					// source table: "wp_users" 
	collname:"wp_users",				// target collection name: "wp_users"
	path:"[ID]"							// target path (inside collection): mapped by [ID] field from wp_users table
	},
										
{name:"users_meta", type: "t2docs", 	// mapping named "users_meta" type: "t2docs" (table as documents)	
	table: "wp_usermeta",				// source table: "wp_usermeta" 
	parent: "users",					// parent collection name: "users" (the mapping/set of data is nested)
	path:"[user_id].meta.[meta_key]",	// target path: [user_id] - (parent connector), "meta" - parent collection elment, [meta_key] - key 
	value:"[meta_value]"				// [meta_value] - value to be placed under the target path (currently only simply field value supported)
	}
]}
 
 
The collection(the effect):
---
 
	{
		_id:1.0,
		ID:1.0,	
		display_name:"admin",
		meta:{ 
			"admin_color" : "fresh" , 
			"closedpostboxes_ant_elems" : "a:1:{i:0;s:16:\"commentstatusdiv\";}" , 
			"comment_shortcuts" : "false" , 
			"description" : "Just testing" , 
			"dismissed_wp_pointers" : "wp330_toolbar,wp330_media_uploader,wp330_saving_widgets" , 
			"first_name" : "John" , 
			"last_name" : "TheAdmin" , 
			"wp_user-settings-time" : "1360265776" , 
			"wp_user_level" : "10"
			....
			}
		user_email:mbisz@wp.pl
		user_login:admin
		user_nicename:admin
		user_pass:$P$BtC.w7H6gOdMRhht5TkBMIWD/xIiAB.
		user_registered:2012-04-25 06:36:01
		....
	} 
	.... 
 

Quick start:
===========

Let's go through whole process from the begining to the end. 

The start is similiar to this sample MySQL setup:
http://dev.mysql.com/doc/employee/en/employees-installation.html


- get sample MySQL database from https://launchpad.net/test-db/

- unpack ("tar -xjf" , 7zip etc) 

- run import through mysql commandline tool:

	mysql -u[username] -p[password] -t < employees.sql
	
	
Now it's time to take a look at the db schema:
	
	http://dev.mysql.com/doc/employee/en/sakila-structure.html
	
	
Moving from relational to non-relational in case of salaries/employees/titles seems to be simple and rather obvious:
Let's get employees as main collection here and titles and salaries as nested documents:

	{
	{name:"employees", type: "t2coll", 			// mapping named "employees" type "t2coll" (table to collection)
		table: "employees", 					// source table: "employees" 
		collname:"employees",					// target collection name: "employees"
		path:"[emp_no]"							// target path: mapped by [emp_no] field from employees table
		},	
		 
	{name:"employees_salaries", type: "t2docs", // mapping named "employees_salaries" type: "t2docs" (table to documents)	
		table: "salaries",						// source table: "salaries" 
		parent: "employees",					// parent collection name: "employees" 
		path:"[emp_no].salaries.[from_date]",	//  "from_date" is unique and  meaningfull - can play as salary identifier inside employee
		}, 										// "value" omitted - the default is to place all record data as key:value pairs
	
	{name:"employees_titles", type: "t2docs", 	// mapping named "employees_titles" type: "t2docs" (table to documents)	
		table: "titles",						// source table: "titles" 
		parent: "employees",					// parent collection name: "employees" 
		path:"[emp_no].titles.[from_date]",		//  "from_date" is ok - as above 
		} 										// "value" omitted - the default is to place all record data as key:value pairs
	}

Now when we have some "r2n" mapping scrap - let's do some action:

- put above mapping (without comments and empty lines) into file "example/employees.r2n"
- cd example & export mysql data as full insert statements:

	mysqldump -u[username] -p[password] --skip-extended-insert --complete-insert employees employees salaries titles > employees.sql
	
- now run the example converter:

	php r2nconvert.php employees 100
	
(convert employees.sql to employees(n).json using mapping employees.r2n each 100k records creating new file)	
	
Notice: You have to have enough RAM memory for parent collections cache (collections which have children) - about 100MB/300k records. 
(currently implemented as in-memory PHPGENCreator.fieldDataCache)
Or You have to partition Your DDL input (in such a way that parent records are inside the same partition as children).
	
	


