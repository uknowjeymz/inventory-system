The UCC-I.M.S. system is an inventory management solution designed to efficiently record, monitor, and manage tools and equipment across various departments, rooms, and laboratories at the University of Caloocan City. It provides a centralized database of inventory information and enables authorized users to update and track tool conditions, including availability, usage, damage, and maintenance. The system aims to enhance accountability, data accuracy, and operational efficiency in the management of university resources.

mga nabago sa system:

- change title from UCC Labtech to UCC Inventory Management System
- change the logo from Laptop to UCC logo
- fixed problem example:
1. Locations - hindi makapag add ng Office/Department dahil sa mga sumusunod:
-> Floors, dahil nagkakabangaan sa id
2. 

- Add Equipment, all Equipments added the Upload Image
- Uploaded Image seen in the View Detail in one item/equipment

changes as of 2:37pm

- improve UI of Add Equipment and View Details of one item/equipment
- improve UI of Equipment Details in inventory_room_detail.php

changes as of 4:21pm

- added Generate Report of Room via PDF
> p'wede mag generate ng report of room via PDF, parang list ng inventories sa isang room / lab, example:
Example:
if mag ge-generate ka, Select Asset Group, naka filter yan na selected ang Computer, Accessories, at Furnitures, or... All inventory items. then kaboom. :3

1-26-26

changes as of 11:42am

- xlsx excel file can now upload for adding item/equipment
- testing xslx excel file, can upload for all Equipments
- added selection for campus in adding equipment/item

1-27-26

changes as of 11am

- sample table structure can click to enlarge and see clearly the photo
- condemned page fixed problem
> instead of deletion and it sees na pending ang condemn item, p'wede na ito ma-transfer from computer, office, lab, general, kitchen table to condemned_equipment table
for complete condemned item and it can't delete na.
- campus included in the following:
> locations
> location types
- generating report include the campus
- generate report include the UCC Logo + Caloocan Logo

changes as of 4pm
- condemnation and deployment with real-time of date and time
- comsumable migrated on this system

1-28-26

changes as of 1:30pm
- Item Number of adding the item/equipment are automate
- Cost added on the adding the item/equipment
- Property Acknowledgment Receipt via PDF created

1-29-26

changes as of 10:08pm
- transferring inventory added, includes the history of transfer inventories and generate report for that
- Edit Inventory added in all_equipment.php, includes in the Item Specification Modal

1-30-26

changes as of 11:12am
- Inventory Report added, including selecting campuses and accountable person

changes as of 5:22pm
- Forgot Password added
- Email Verification added
- Serial/Property No. added in the Generate Transfer report
- Change Remarks to Accountable person
- Change Article (for Computer Equipment) to Desktop Type with dropdown and selections
- Two Serials in Computer Package (on Computer Equipment)
- Article of General Equipment change to Dropdown with selections

2-2-26

changes 12:37pm

- Label of No. of Supplies removed
- Current "Inventory" added in generating report
- Show/Hide password added
- Generate report in every item added, including the room assignment of where it assigned
- Generate PAR removed, with modal on it
- Generate stock report via pdf and xlsx added
- fix register.php from assigned "user" to "admin"
- send_otp.php fixed issue of same email add receiving
- Consumable improve UI/UX
- Refill button added in Consumable item, it appears once the item are in quantity of 10 below
- Release button added in Release History in Consumable, it will Release the pending of requested consumable item
- Improve UI/UX table of all_equipment.php

2-3-26

changes 11:07am

- fix issue of generate_room_report.php

2-20-26

Revisions of the following:

- Individuals report in consumables release history = DONE ✅✅✅
- PRS Form for condemned report format = DONE ✅✅✅
- Add year acquired in all equipment report = DONE ✅✅✅
- PAR format in equipment = DONE ✅✅✅

Added:

System Developers added in index.php

- modified process_equipment.php

changes as of 8:41pm

- Excel of PRS, removal the check of For disposal
- In Consumable:
> No manual entry has been implemented
> Auto generate code of adding the consumable item and requesting consumable item
> Summary of refill modified
> Threshold of Stocks/Quantity added
> Modified Stock Report
> Request Consumable Item Improved
> Button Request for Item - One time entry of details implemented
- improved process_equipment.php
- Filters in all_equipment.php are back

2-21-26

changes as of 1:05am

- Consumable Management System deployed in mobile phone (local first)
- Can add to cart, request many items in one group
- Can auto generate account credential once it creates an account, comes with Terms and Conditions AND Data Privacy Act of 2012
- All of consumable items fetched in consumables table

2-22-26

changes as of 4:55pm

Consumable Management System modified the following:
- on My Requests, Generate report added on each requested items
- Profile and Settings added, on settings, can change password for more secure

- consumables.php modified, check requested items first before approval or rejecting
- on rejecting the items, there's a rejection reason to input of rejection reasons

changes as of 5:42pm

- register.php and users.php modified, added the Department and Phone Number

2-23-26

changes as of 1:03am

- Consumable Management System added favicon
- Consumable Management System have QR Code to scan whenever (applicable in live hosting)
all_equipment.php modified the following:
- Purchase Date added
- Edit Equipment Details modified, including Modal UI
- Complete Details of Added Item/s, including the all equipment details

- some pages added favicon

changes as of 1:44am

Consumable Management System added the following:
- Forgot Password added
- Reset Password added

2-25-26

changes as of 3:04am

- on adding equipment, the input type of Accountable Person changed to Last Name, First Name, and Middle Initial, same on editing equipment
- Generate PAR modified, it selects each item, generates accountable person's equipment
- Users Management on Admin functional, like Adding and Editing user account (both roles)
- UI/UX Improvement to make it look better than the old one
- Comsumable Management System added the Rejected status
- Email Address changed in Mail Sender

changes as of 4:46am
- Notifications implemented for Consumable and Inventory Management System
- Real-time notification of receiving an Requested Consumable Item on the admin of Inventory Management System
- Condemned Equipment added the Purchase Date
- Transfer History modified, the Accountable Person remains, but changes on the input type, changed to Last Name, First Name, and Middle Initial
- Transfer Report modified the Accountable Person, can see in the report of Prev. Accountable Person and New Accountable Person

changes as of 3:25pm

- all_equipment.php UI/UX improved
- transfer_history.php UI/UX improved

changes as of 4:57pm

- fixed issue of Add Equipment in all_equipment.php
- fixed background of Exit in each modal

2-26-26

- Edit Consumable Item applied in Consumable Management (Admin side) from the Current Inventory
- change of UCC Background on login page
- fix header.php issue

2-27-26

- In Consumable Management, from the Request Multiple Items, change the Office/Department to Dropdown with the lists of Departments
- In Consumable Management, from Current Inventory, add the Filters that fetches the Category
- applied change of Received From and Received By from the Generate PAR into auto generated name

changes as of 1:59pm

- Monthly Consumption and Annual Summary & Category Report implemented and improved UI/UX of that in Consumable Management (Admin side)
- on Request Multiple Items from adding an Consumable Item, "Others" added in dropdown of Office/Department, with input type appearing after selecting Others
- Back to Top added
- buttons of Request History and Consumption Reports added to scroll automatically on designated
- generate reports of Monthly Consumption, Annual Summary & Category Report implemented via PDF and Excel

2-28-26

changes as of 5:29am

- computers.php, inventory_monitor.php and inventory.php from the admin move to archive_sites as temporary no use
- modified consumables.php by removing chart
- all_equipment.php modified, making the assignment in functionally
- room_assignments.php and inventory_room_detail.php modified UI/UX and make that site functionally
- settings.php implemented
- regard with the settings, dropdown from the Office/Department of the Request Multiple Items in the Consumable (Admin side) change to dynamically fetched Office/Department
- dynamically Office/Department from the Request Multiple Items can add Office/Department and edit details
- dashboard.php modified, change the reports to Consumable (Mobile Site) to visit the Consumable Management System website in desktop

3-1-26

changes as of 11:57pm

- fix issue of adding an equipment items in all_equipment.php and process_equipment.php
- on the crucial issue of date picker, the date picker on each equipment item editing will always seen, no more crucial issue of that

3-3-26

changes as of 2:29am

- Manual Condemned implemented in all_equipment.php
- can archived the condemned item instead of deletion the data
- Threshold set on Consumable Items:
Critical Stock: 10 ream, 30 pcs, 10 unit, 10 box, etc.
Low Stock: 11-20 unit, 11-20 ream, 31-50 pcs, 11-20 box
Available: 21+ unit, 21+ ream, 51+ pcs, 21+ box
- adding of quantity in Consumable Item modified, adds the date of quantity added

changes as of 1pm

- Change the color to Green (Assignment History) = RENZ = ✅
- All new request should be on top (in Consumable) = RENZ = ✅ GREG DID THAT
- Add Date to when consumable item is added, also in quantity if the item was critical stock (in Consumable) = RENZ = ✅ BOTH DID THAT
- Remove/Change Campus To > From to Accountable Person = RENZ = ✅ = GREG DID THAT

3-4-26

changes as of 3:45am

- generate_release_report.php fixed issue
- Separate Additional Item of Consumable Item are implemented in Refill Button
NOTE: Instead of editing the quantity, the Refill button can refill Consumable Item anytime even the Item was Available, Low Stock, and Critical Stock if the item was unexpectedly added physically
- Request History on the Consumable Admin side modified the sorting to Date Added/Date Requested
- on generate_transfer_report.php, change the Campus From --> To, to Previous Accountable Person
- Choices of Campus on Requesting an Consumable Item added in Admin side
- Choices of Campus and Dynamic of Office/Department added in register.php
- users.php and header.php modified by seeing the Campus assigned
- Date of Added Consumable Item implemented
- Low Stock item can still request item, but it can't max the quantity of item, it limits it when the item reaches to Critical Stock
- Consumable Mobile side modified by the following:
--> on Requesting an Consumable Item, if the Item was Critical Stock, it can't request/add to cart on it
--> Toast Notification added after requesting/add an item to cart
--> Shortcut of cart.php added in bottom right side of dashboard.php
--> Low Stock item can still add to cart/request item, but it can't max the quantity of item, it limits it when the item reaches to Critical Stock
--> Choices of Campus and Dynamic of Office/Department added and implemented