// Express and routing
const express = require('express');
const router = express.Router();

// Passport
const passport = require('passport');

// DB/Sequelize
const db = require(__dirname + "/../../lib/db.js");
const sequelize = require(__dirname + "/../../lib/sequelize.js");
const { Op } = require("sequelize");

// Misc libraries
const uuid = require("uuid");
const moment = require("moment");
const authTokenManager = require(__dirname + '/../../lib/etc/authToken/manager.js');
const adminManager = require(__dirname + '/../../lib/etc/admin/manager.js');
const uuidManager = require(__dirname + '/../../lib/etc/uuid/manager.js');
const passwordManager = require(__dirname + '/../../lib/etc/password/manager.js');

// Import sequelize models used for these routes
const authToken = require(__dirname + '/../../lib/models/authtoken.js')(sequelize);
const admin = require(__dirname + '/../../lib/models/admin.js')(sequelize);

//Body Parser to parse the JSON requests
const bodyParser = require('body-parser');
var jsonParser = bodyParser.json();

async function checkUsernameExists(username) {
    var usernameFound = false;
    // Check if username or email exists
    await admin.findOne({
        attributes: ['adminId'],
        where: sequelize.where(sequelize.fn('lower', sequelize.col('username')), username.toLowerCase())
    })
    .then(result => {
        console.log(result);
        if (result !== null) {
            usernameFound = true;
        }
    })
    .catch(err => {
        console.log(err);
        throw new Error(err);
    });
    return usernameFound;
}

function sendStandardRes(res, errors) {
    var status;
    if (errors.length > 0) {
        status = 'error';
        // console.log(errors);
    } else {
        status = 'success';
    }

    res.send({
        "status": status,
        "errors": errors
    });
}

router.get('/', (req, res) => {
    console.log("GET Request recieved - Admin: " + req.body);
    res.send({
        "status": "error",
        "errorMessage":"This is not an endpoint."
    });
});

// router.get('/test', (req, res) => {
//     console.log("GET Request recieved (TEST PAGE) - Admin: " + req.body);

//     res.send({
//         "status": "testing"
//     });

//     authToken.findOne({
//         attributes: ['authTokenId'],
//         where: {
            
//         }
//     })
//     .then(result => {
//         console.log(result);
//     })
// });

router.post('/', jsonParser, (req, res) => {
    console.log("POST Request recieved - Admin: " + req.body);
    res.send({
        "status": "error",
        "errorMessage":"This is not an endpoint."
    });
});

router.get('/testfunction', (req, res) => {
    console.log("GET Request recieved - Admin Test Function: " + req.body);
    res.send({
        "status": "success",
        "Test Message": "Test Admin Function"
    });
});

router.post('/createaccount', jsonParser, (req, res) => {
    console.log("POST Request recieved - Create Account: " + req.body);

    var status = '';
    var errors = [];

    var reqIp = req.headers['x-forwarded-for'] || req.socket.remoteAddress;

    // firstName check
    if (!req.body.firstName || typeof req.body.firstName == 'undefined' || req.body.firstName.length < 2) {
        errors.push({
            type: 'firstName',
            msg: 'Must be at least 2 characters long.'
        });
    }
    
    // lastName check
    if (!req.body.lastName || typeof req.body.lastName == 'undefined' || req.body.lastName.length < 2) {
        errors.push({
            type: 'lastName',
            msg: 'Must be at least 2 characters long.'
        });
    }

    // email check
    if (!req.body.email || typeof req.body.email == 'undefined' || req.body.email.length < 2 || /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test(req.body.email) !== true) {
        errors.push({
            type: 'email',
            msg: 'Enter a valid email address.'
        });
    }

    // username check
    if (!req.body.username || typeof req.body.username == 'undefined' || req.body.username.length < 5) {
        errors.push({
            type: 'username',
            msg: 'Must be at least 5 characters long.'
        });
    }

    // password check
    if (!req.body.password || typeof req.body.password == 'undefined' || req.body.password.length < 10) {
        errors.push({
            type: 'password',
            msg: 'Must be at least 10 characters long.'
        });
    }

    var goOn = true;

    // Check if the authToken exists

    (async () => {
        try {
            if (!await authTokenManager.verify(req.body.authToken, "createAccount", reqIp)) {
                errors.push({
                    type: 'authToken',
                    msg: 'You\'re not authorized to sign up.'
                });
                sendStandardRes(res, errors);
                goOn = false;
            }

            if (goOn) {
                // Check if username exists
                var usernameExists = await checkUsernameExists(req.body.username);
                console.log(usernameExists);
                if (usernameExists) {
                    errors.push({
                        type: 'username',
                        msg: 'This username is already taken.'
                    });
                    sendStandardRes(res, errors);
                    goOn = false;
                }
            }

            if (errors.length == 0 && goOn) {
                // Create user
                var newAdminId = await uuidManager.getNewUuid('admin');
                var hashedPassword = await passwordManager.encrypt(req.body.password);

                admin.create({
                    adminId: newAdminId,
                    username: req.body.username,
                    password: hashedPassword,
                    email: req.body.email,
                    profilePicture: null,
                    allowSignIn: 1,
                    dateTimeJoined: moment().format('YYYY-MM-DD HH:mm:ss'),
                    dateTimeLeft: null,
                    firstName: req.body.firstName,
                    lastName: req.body.lastName
                })
                .then((err) => {
                    sendStandardRes(res, errors);
                    goOn = false;
                })
                .catch(err => {
                    console.log(err);
                    errors.push({
                        type: 'general',
                        msg: 'An unknown error occurred.'
                    });
                    sendStandardRes(res, errors);
                    goOn = false;
                });
            } else {
                sendStandardRes(res, errors);
            }
        } catch (err) {
            console.log(err);
            errors.push({
                type: 'general',
                msg: 'An unknown error occurred and this request could not be fulfilled.'
            });
            sendStandardRes(res, errors);
        }
    })();
    
});

router.post('/login', jsonParser, (req, res) => {
    var errors = [];
    var goOn = true;
    var adminsFound = [];
    var matchedAdminIn;
    console.log("POST Request recieved - Admin Login: " + req.body);

    // username/email check
    if (!req.body.usernameEmail || typeof req.body.usernameEmail == 'undefined' || req.body.usernameEmail.length < 5) {
        errors.push({
            type: 'username',
            msg: 'An admin with this information does not exist.'
        });
    }

    // password check
    if (!req.body.password || typeof req.body.password == 'undefined' || req.body.password.length < 10) {
        errors.push({
            type: 'password',
            msg: 'No match could be found.'
        });
    }

    var reqIp = req.headers['x-forwarded-for'] || req.socket.remoteAddress;

    try {
        (async () => {
            // Check if the authToken exists
            if (!await authTokenManager.verify(req.body.authToken, "adminLogin", reqIp)) {
                errors.push({
                    type: 'authToken',
                    msg: 'You\'re not authorized to log in.'
                });
                sendStandardRes(res, errors);
                goOn = false;
            }

            // Get all users that have the username or email and
            if (goOn && errors.length < 1) {
                adminsFound = await adminManager.getWhere(
                    ['adminId', 'password'], 
                    {
                    [Op.or]: [
                        {username: req.body.usernameEmail},
                        {email: req.body.usernameEmail}
                    ]
                });

                if (adminsFound === null || adminsFound.length !== 1) {
                    errors.push({
                        type: 'usernameEmail',
                        msg: 'An admin with this information does not exist.'
                    });
                    sendStandardRes(res, errors);
                    goOn = false;
                }

                // Check if the hashed password of the admin that was found matches the given password
                if (goOn) {
                    if (!await passwordManager.verify(adminsFound[0].password, req.body.password)) {
                        errors.push({
                            type: 'password',
                            msg: 'Password does not match.'
                        });
                        sendStandardRes(res, errors);
                        goOn = false;
                    }
                }

                // Log in the user
                if (goOn) {
                    // Init the admin session object
                    req.session.admin = {
                        adminId: adminsFound[0].adminId,
                        logInTime: 'now'
                    }
                    // Redirect to overview
                    sendStandardRes(res, errors);
                    console.log(req.body.usernameEmail + " logged in.");
                }
            } else {
                sendStandardRes(res, errors);
                goOn = false;
            }

            // Check if the password matches the found user
            if (goOn) {

            }

            // Check if password matches the selected user in db
        })();
    } catch (err) {
        console.log(err);
        errors.push({
            type: 'general',
            msg: 'An unknown error occurred and this request could not be fulfilled.'
        });
        sendStandardRes(res, errors);
    }
    
});

module.exports = router;