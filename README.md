# esycrmm
this is EsyCRM open source. This is now open source and free to use as you wish. No gurrantee or lic will come with it. Your contribute for further development of this FREE CRM which is running in https://esycrm.com with thousands of business users.

## customization for your organization
Yes we have a remote experienced team to customized or create new module for your business any time. Kindly contact us through https://esycrm.com


## ESY CRM

Customer Relationship Management (CRM) is a system that manages customer interactions and data throughout the customer life-cycle between the customer and the company across different channels. In this tutorial, we are going to build a custom CRM in PHP, which a sales team can use to track customers through the entire sales cycle.

We’ll be creating a simple CRM system for salespeople to:

Access their tasks
View their leads
Create new tasks for each lead
Create new opportunity
Close a sale
Sales managers will be able to:

Manage all customers
Manage sales team
View current sales activities
Download Demo Files

Building Blocks of a CRM
Here is a list of the essential components of the CRM:

Leads: initial contacts
Accounts: Information about the companies you do business with
Contact: Information about the people you know and work with. Usually, one account has many contacts
Opportunities: Qualified leads
Activities: Tasks, meetings, phone calls, emails and any other activities that allow you to interact with customers
Sales: Your sales team
Dashboard: CRM dashboards are much more than just eye candy. They should deliver key information at a glance and provide links to drill down for more details.
Login: Salespeople and managers have different roles in the system. Managers have access to reports and sales pipeline information.

System Requirements
PHP 5.3+,
MySQL or MariaDB
Create CRM Database
We will start by creating our custom CRM database. The main tables we will be using are:

contact — contains basic customer data
notes — holds information collection from Contact by sales people.
users — information about sales people

The Contact table contains basic customer information including names, company addresses, project information, and so forth.

The Notes table stores all sales activity information such as meetings and phone calls.

The Users table holds login information about users of the system such as usernames and passwords. Users can also have roles, such as Sales or Manager.

All other tables are lookup tables to join to the three main relational database tables.

contact_status — contains contact status such as Lead and Opportunity. Each indicates a different stage in a typical sales cycle
task_status — the task status can be either Pending or Completed
user_status — a sale person can be Active or Inactive
todo_type — a type of task either Task or Meeting
todo_desc — description of a task such as Follow Up Email, Phone Call, and Conference etc.
roles — a user can be either a Sales Rep or a Manager

Login: email + 123456 / 12345678

## how to install or start developing

Previously create with full Docker but as we needed to run the application into less expensive server so we needed to convert the docker server application to direct php application

### clone this repository and then change branch to booking

* install PHP 7.4
* install Mysql 5.6
* install apache 2.4
* install most of the PHP libraries
* run composer install
* change the environment variable
* create virtual host [esycrmapp.lan] with apache
* run the application
* fill the database
* run a query in mysql: SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));

