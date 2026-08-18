# Phase 7 Screen-wise Field Specification

## Equipment Dashboard
Cards: Total Assets, Active on Projects, Under Maintenance, GPS Offline, Fuel Cost This Month, Maintenance Due, Average Utilization, Unposted Equipment Costs.

## Equipment / Vehicles
Fields: Asset Code, Asset Name, Asset Type, Category, Plate Number, Serial Number, Model, Manufacturer, Year, Ownership Type, Purchase Cost, Rental Rate, Depreciation Method, Fuel Type, GPS Device ID, Current Odometer, Current Working Hours, Status.

## Project / Site Assignments
Fields: Asset, Project, Site, Operator / Driver, Start Date, End Date, Assignment Type, Hourly Rate, Daily Rate, Status.

## GPS Tracking
Fields: Asset, Latitude, Longitude, Speed, Idle Minutes, Last Ping At, Geofence Status, Device Battery, GPS Status.

## Maintenance Jobs
Fields: Job Number, Asset, Maintenance Type, Issue Description, Scheduled Date, Completed Date, Mechanic, Odometer / Hours Reading, Parts Cost, Labor Cost, Total Cost, Downtime Hours, Status.

## Fuel Logs
Fields: Fuel Log Number, Asset, Project, Site, Fuel Date, Fuel Type, Liters, Rate, Total Amount, Odometer / Hours Reading, Receipt Upload, Status.

## Documents
Types: Registration, Insurance, Inspection Certificate, Permit, Rental Agreement, Other.
Fields: Asset, Document Type, Document Number, Issue Date, Expiry Date, File Path, Status.

## Operators / Drivers
Use Phase 3 employees. Fields: Employee, License Number, License Expiry, Assigned Asset, Training Status, Status.

## Utilization
Metrics: Working Hours, Idle Hours, Maintenance Downtime, Utilization %, Fuel Efficiency, Cost Per Hour, Project Usage.

## Project Equipment Costing
Sources: assignment usage, fuel logs, maintenance jobs, rental bills, depreciation, spare parts later from Phase 6 inventory.

## Reports
Fleet Status, Fuel Consumption, Maintenance Cost, Utilization, GPS Offline, Document Expiry, Project Equipment Cost.
