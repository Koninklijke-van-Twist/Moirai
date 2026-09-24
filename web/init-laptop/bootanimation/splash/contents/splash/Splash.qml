import QtQuick

Rectangle {
    id: root
    color: "black"

    property int stage
    property real fadeMs: 700
    property real d1: 1
    property real d2: 0
    property real d3: 0
    // 0 = idle, 1 = fade in, 2 = fade out
    property int s1: 2
    property int s2: 0
    property int s3: 0
    property bool t1: false
    property bool t2: false
    property bool t3: false
    property real lastNow: 0

    onStageChanged: {
        if (stage >= 5)
            outro.running = true;
    }

    function nextOf(n) {
        if (n === 1)
            return 2;
        if (n === 2)
            return 3;
        return 1;
    }

    function startIn(n) {
        if (n === 1) {
            s1 = 1;
            t1 = false;
        } else if (n === 2) {
            s2 = 1;
            t2 = false;
        } else {
            s3 = 1;
            t3 = false;
        }
    }

    function stepDot(n, dt) {
        var speed = dt / fadeMs;
        var op;
        var st;
        var trig;
        if (n === 1) {
            op = d1;
            st = s1;
            trig = t1;
        } else if (n === 2) {
            op = d2;
            st = s2;
            trig = t2;
        } else {
            op = d3;
            st = s3;
            trig = t3;
        }

        if (st === 1) {
            op = Math.min(1, op + speed);
            if (op >= 1) {
                op = 1;
                st = 2;
                trig = false;
            }
        } else if (st === 2) {
            var prev = op;
            op = Math.max(0, op - speed);
            if (!trig && prev > 0.5 && op <= 0.5) {
                trig = true;
                startIn(nextOf(n));
            }
            if (op <= 0) {
                op = 0;
                st = 0;
                trig = false;
            }
        }

        if (n === 1) {
            d1 = op;
            s1 = st;
            t1 = trig;
        } else if (n === 2) {
            d2 = op;
            s2 = st;
            t2 = trig;
        } else {
            d3 = op;
            s3 = st;
            t3 = trig;
        }
    }

    Timer {
        interval: 16
        running: outro.running ? false : true
        repeat: true
        onTriggered: {
            var now = Date.now();
            if (root.lastNow === 0)
                root.lastNow = now;
            var dt = Math.min(48, now - root.lastNow);
            root.lastNow = now;
            if (dt <= 0)
                return;
            root.stepDot(1, dt);
            root.stepDot(2, dt);
            root.stepDot(3, dt);
        }
    }

    Item {
        id: content
        anchors.fill: parent

        Image {
            anchors.fill: parent
            asynchronous: false
            fillMode: Image.PreserveAspectFit
            source: "images/main.png"
            smooth: true
        }
        Image {
            anchors.fill: parent
            asynchronous: false
            fillMode: Image.PreserveAspectFit
            opacity: root.d1
            source: "images/dot1.png"
            smooth: true
        }
        Image {
            anchors.fill: parent
            asynchronous: false
            fillMode: Image.PreserveAspectFit
            opacity: root.d2
            source: "images/dot2.png"
            smooth: true
        }
        Image {
            anchors.fill: parent
            asynchronous: false
            fillMode: Image.PreserveAspectFit
            opacity: root.d3
            source: "images/dot3.png"
            smooth: true
        }
    }

    OpacityAnimator {
        id: outro
        running: false
        target: content
        from: 1
        to: 0
        duration: 400
        easing.type: Easing.InOutQuad
    }
}
