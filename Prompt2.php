
     when open in the Google Map... ERROR : The Google Maps Embed API must be used in an iframe.,
use free MAP api I don't have google API rights, 
===============
1. Need to update the report/print part, In Printing this columns should be accoumandate in table format like Roll No.,Name,Paper in a box with Signature (Blank Column for candidate signature)  , most of the time the Rows are 5 and Columns are 6 make follow the table in this format and numbering start from Top Left 1st and Below That 2nd and So on upto 6th place then in next column 7th and below that 8th and so on...means Numbering from 1-6,7-12,13-18,19-24 like wise verticle numbring
and remove the table below
===============
1. Need to upload (attached room-wise) PDF files, with details of each file having details of year-wise items/assets issued or received, that is, in a PDF file maintained manually by the user, and upload/attach here with each room, respectively, so that anyone can find out the details of the same year-wise. This field can be created in the add/edit modal before the "Room Image" field, accordingly save information in a table so that a single SQL query can also update the table, and all use tables fields/structure can be sought from the table.sql file.
2. Need to add a Project / Paper column that will not be fixed, but also be a column in CSV, so that multiple paper names can be added
3. Scroll Bar Need to see the bottom of the text and matrix under (This section needs to be scrolled) Seat Builder > Layout Type > TEACHER / INVIGILATOR DESK
4. Show all rooms that will occur on the same date of examination with a check box and user selected for Print Section, so that it should first get selected by the user a Room and then print on the A4 sheet paper (Make a Squeeze a layout maximum so that two rooms details must be printed on each A4 Sheet Vertically, so that paper would be saved.)

====================================================================================================================
Need to add in the Examination Seat Booking Modal
1. Timing  with AM PM
2. Shift (Morning or Evening)
(Make an Good Logic for the Timing and shift)
A Comprehensive Search/Filter for Examination For student/candidate work as an sperate program like igipess_exam_seach.php
will search throughout the database from examination seat booking modal and let the show there ROOM NO. and other details get it from the Room Matrix so that he/she can easy access the same.

In Printing add one more column :Signature (Blank Column for candidate signature)
1. Instead of Each Note for every candidate make one Remarks and that should be printed at bottom
2. Paper Name also be printed at Top
3. Print at a top Right a QR-Code of location of Room Link scanned by examiner to directly understand the Room Location






==============================================================================================================

Need to update seat matrix from CSV file directly with the following details :
in Examination Seat Booking Modal update the following and update from via CSV
Room  Fixed Room 7-8 - Computer Lab  (Fixed selected/filled by user)
Exam Date 19-05-2026 (Fixed filled by user)
Status :Reserved  (Fixed selected/filled by user)
Project / Paper : ((Fixed filled by user))
Seat Label From CL1,CL2,CL3.... to CL40 (Fill in CSV)
Roll No. From 8445,8446,8447.... to ... (Fill in CSV)
Candidate Name (in Antoher Column fill in CSV)
Notes (in Antoher Column fill in CSV, if any fill)